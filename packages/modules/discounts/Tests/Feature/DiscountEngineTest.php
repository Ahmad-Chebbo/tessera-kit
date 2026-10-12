<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Discounts\Engine\RuleExpression;
use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Sales\Actions\CheckoutAction;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;

function sellItems(array $items, ?Customer $customer = null, array $options = []): Order
{
    $test = test();

    return app(CheckoutAction::class)->execute(
        branch: $test->branch,
        cartItems: array_map(fn (array $item): array => ['product_id' => $item[0]->id, 'quantity' => $item[1]], $items),
        cashReceived: 100000,
        cashier: $test->cashier,
        discountType: $options['discount_type'] ?? null,
        discountValue: $options['discount_value'] ?? 0,
        customerId: $customer?->id,
        paymentType: $options['payment_type'] ?? 'cash',
        couponCode: $options['coupon'] ?? null,
    );
}

function makeRule(array $attributes = []): DiscountRule
{
    return DiscountRule::factory()->create(['company_id' => test()->company->id, ...$attributes]);
}

function pastPurchase(Customer $customer, Product $product, float $quantity, int $daysAgo = 0, OrderStatus $status = OrderStatus::Completed): Order
{
    $test = test();
    $order = Order::create([
        'company_id' => $test->company->id, 'branch_id' => $test->branch->id, 'order_number' => 'OLD-'.uniqid(),
        'subtotal' => 100, 'total' => 100, 'status' => $status, 'cashier_id' => $test->cashier->id,
        'customer_id' => $customer->id, 'payment_type' => 'cash',
    ]);
    $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 100, 'cost_price' => 50, 'total' => 100 * $quantity]);
    $order->forceFill(['created_at' => now()->subDays($daysAgo)])->save();

    return $order;
}

beforeEach(function () {
    $this->company = Company::factory()->create(['timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->cashier = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
    $this->drinks = Category::factory()->create(['company_id' => $this->company->id]);
    $this->snacks = Category::factory()->create(['company_id' => $this->company->id]);
    $this->cola = Product::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->drinks->id, 'base_price' => 100, 'cost_price' => 50, 'unlimited_quantity' => true, 'is_active' => true]);
    $this->chips = Product::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->snacks->id, 'base_price' => 50, 'cost_price' => 20, 'unlimited_quantity' => true, 'is_active' => true]);
    $this->customer = Customer::create(['company_id' => $this->company->id, 'name' => 'Rana']);
});

describe('actions', function () {
    it('takes a percentage off matching lines only', function () {
        makeRule(['name' => 'Drinks 20%', 'action_value' => 20])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.category_id', 'operator' => 'eq', 'value' => $this->drinks->id],
        ]]]);

        $order = sellItems([[$this->cola, 2], [$this->chips, 2]]);

        expect((float) $order->subtotal)->toBe(300.0)
            ->and((float) $order->rules_discount_amount)->toBe(40.0)
            ->and((float) $order->discount_amount)->toBe(40.0)
            ->and((float) $order->total)->toBe(260.0);

        $cola = $order->items->firstWhere('product_id', $this->cola->id);
        expect((float) $cola->discount_amount)->toBe(40.0)
            ->and((float) $order->items->firstWhere('product_id', $this->chips->id)->discount_amount)->toBe(0.0);

        $application = DiscountApplication::firstOrFail();
        expect($application->rule_name)->toBe('Drinks 20%')->and((float) $application->amount)->toBe(40.0)->and($application->order_item_id)->toBe($cola->id);
    });

    it('applies fixed amounts per unit, per line and across the whole match', function () {
        makeRule(['action_type' => DiscountAction::Fixed, 'action_value' => 5, 'action_config' => ['apply_to' => 'each_unit']]);
        expect((float) sellItems([[$this->cola, 3]])->rules_discount_amount)->toBe(15.0);

        DiscountRule::query()->delete();
        makeRule(['action_type' => DiscountAction::Fixed, 'action_value' => 5, 'action_config' => ['apply_to' => 'each_line']]);
        expect((float) sellItems([[$this->cola, 3], [$this->chips, 1]])->rules_discount_amount)->toBe(10.0);

        DiscountRule::query()->delete();
        makeRule(['action_type' => DiscountAction::Fixed, 'action_value' => 30, 'action_config' => ['apply_to' => 'total']]);
        $order = sellItems([[$this->cola, 1], [$this->chips, 1]]);
        expect((float) $order->rules_discount_amount)->toBe(30.0)
            ->and((float) $order->items->firstWhere('product_id', $this->cola->id)->discount_amount)->toBe(20.0)
            ->and((float) $order->items->firstWhere('product_id', $this->chips->id)->discount_amount)->toBe(10.0);
    });

    it('never discounts a line below zero', function () {
        makeRule(['action_type' => DiscountAction::Fixed, 'action_value' => 500, 'action_config' => ['apply_to' => 'each_line']]);

        $order = sellItems([[$this->chips, 1]]);

        expect((float) $order->total)->toBe(0.0)->and((float) $order->discount_amount)->toBe(50.0);
    });

    it('sets a special price', function () {
        makeRule(['action_type' => DiscountAction::Price, 'action_value' => 80])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.product_id', 'operator' => 'eq', 'value' => $this->cola->id],
        ]]]);

        $order = sellItems([[$this->cola, 3], [$this->chips, 1]]);

        expect((float) $order->rules_discount_amount)->toBe(60.0)->and((float) $order->total)->toBe(290.0);

        $cheap = Product::factory()->create(['company_id' => $this->company->id, 'base_price' => 60, 'unlimited_quantity' => true, 'is_active' => true]);
        DiscountRule::query()->update(['conditions' => null]);
        expect((float) sellItems([[$cheap, 2]])->rules_discount_amount)->toBe(0.0);
    });

    it('gives free units for buy X get Y', function () {
        makeRule(['action_type' => DiscountAction::BuyXGetY, 'action_value' => 0, 'action_config' => ['buy_quantity' => 10, 'get_quantity' => 1]]);

        expect((float) sellItems([[$this->cola, 10]])->rules_discount_amount)->toBe(0.0);
        expect((float) sellItems([[$this->cola, 11]])->rules_discount_amount)->toBe(100.0);
        expect((float) sellItems([[$this->cola, 23]])->rules_discount_amount)->toBe(200.0);

        DiscountRule::query()->update(['action_config' => ['buy_quantity' => 2, 'get_quantity' => 1, 'get_discount_percent' => 50]]);
        expect((float) sellItems([[$this->cola, 3]])->rules_discount_amount)->toBe(50.0);
    });

    it('caps the total a rule can take off', function () {
        makeRule(['action_value' => 50, 'action_config' => ['cap_amount' => 30]]);

        $order = sellItems([[$this->cola, 2], [$this->chips, 2]]);

        expect((float) $order->rules_discount_amount)->toBe(30.0);
    });
});

describe('conditions', function () {
    it('targets one customer, a customer group, or nobody in particular', function () {
        $vip = CustomerGroup::factory()->create(['company_id' => $this->company->id]);
        $member = Customer::create(['company_id' => $this->company->id, 'name' => 'Member', 'customer_group_id' => $vip->id]);

        makeRule(['name' => 'VIP', 'action_value' => 10])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'customer.group_id', 'operator' => 'eq', 'value' => $vip->id],
        ]]]);
        makeRule(['name' => 'Rana only', 'action_value' => 5, 'stackable' => true])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'customer.id', 'operator' => 'in', 'value' => [$this->customer->id]],
        ]]]);

        expect((float) sellItems([[$this->cola, 1]], $member)->rules_discount_amount)->toBe(10.0)
            ->and((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(5.0)
            ->and((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
    });

    it('rewards a customer who bought an item ten times before', function () {
        makeRule(['name' => 'Loyal', 'action_value' => 15])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.bought_quantity', 'operator' => 'gte', 'value' => 10],
        ]]]);

        pastPurchase($this->customer, $this->cola, 9);
        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);

        pastPurchase($this->customer, $this->cola, 1);
        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(15.0)
            ->and((float) sellItems([[$this->chips, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);
    });

    it('counts orders, spend and category purchases inside a time window and ignores voided orders', function () {
        makeRule(['name' => 'Window', 'action_value' => 10])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'customer.orders_count', 'operator' => 'gte', 'value' => 3, 'params' => ['window_days' => 30]],
        ]]]);

        pastPurchase($this->customer, $this->cola, 1, 5);
        pastPurchase($this->customer, $this->cola, 1, 10);
        pastPurchase($this->customer, $this->cola, 1, 90);
        pastPurchase($this->customer, $this->cola, 1, 2, OrderStatus::Voided);

        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);

        pastPurchase($this->customer, $this->chips, 1, 1);
        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(10.0);

        DiscountRule::query()->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.category_bought_quantity', 'operator' => 'gte', 'value' => 2, 'params' => ['window_days' => 30]],
        ]]]);
        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(10.0)
            ->and((float) sellItems([[$this->chips, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);

        DiscountRule::query()->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'customer.total_spent', 'operator' => 'between', 'value' => [250, 300]],
        ]]]);
        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);
    });

    it('supports nested all and any groups', function () {
        makeRule(['action_value' => 10])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'cart.subtotal', 'operator' => 'gte', 'value' => 100],
            ['type' => 'group', 'logic' => 'any', 'children' => [
                ['type' => 'condition', 'field' => 'item.product_id', 'operator' => 'eq', 'value' => $this->cola->id],
                ['type' => 'condition', 'field' => 'item.category_id', 'operator' => 'in', 'value' => [$this->snacks->id]],
            ]],
        ]]]);

        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(10.0)
            ->and((float) sellItems([[$this->chips, 2]])->rules_discount_amount)->toBe(10.0)
            ->and((float) sellItems([[$this->chips, 1]])->rules_discount_amount)->toBe(0.0);
    });

    it('matches on quantity, branch, payment type and negations', function () {
        makeRule(['name' => 'Bulk', 'action_value' => 10])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.quantity', 'operator' => 'gte', 'value' => 5],
            ['type' => 'condition', 'field' => 'context.payment_type', 'operator' => 'eq', 'value' => 'cash'],
            ['type' => 'condition', 'field' => 'context.branch_id', 'operator' => 'not_in', 'value' => [999]],
            ['type' => 'condition', 'field' => 'customer.is_selected', 'operator' => 'eq', 'value' => false],
        ]]]);

        expect((float) sellItems([[$this->cola, 5]])->rules_discount_amount)->toBe(50.0)
            ->and((float) sellItems([[$this->cola, 4]])->rules_discount_amount)->toBe(0.0)
            ->and((float) sellItems([[$this->cola, 5]], $this->customer)->rules_discount_amount)->toBe(0.0);
    });

    it('matches on weekday, time and date in the company timezone', function () {
        $this->company->update(['timezone' => 'Asia/Beirut']);
        $this->travelTo(CarbonImmutable::parse('2026-10-14 08:30:00', 'UTC'));

        makeRule(['name' => 'Happy hour', 'action_value' => 10])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'context.weekday', 'operator' => 'in', 'value' => [3]],
            ['type' => 'condition', 'field' => 'context.time', 'operator' => 'between', 'value' => ['11:00', '12:00']],
            ['type' => 'condition', 'field' => 'context.date', 'operator' => 'gte', 'value' => '2026-10-01'],
        ]]]);

        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(10.0);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 12:30:00', 'UTC'));
        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
    });

    it('ignores unknown fields instead of failing', function () {
        makeRule()->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'nonsense.field', 'operator' => 'eq', 'value' => 1],
        ]]]);

        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
    });
});

describe('expressions', function () {
    it('applies a rule from a custom expression', function () {
        makeRule(['name' => 'Expr', 'action_value' => 10, 'expression' => 'customer.is_selected and item.category_id == '.$this->drinks->id.' and item_bought(30) >= 2']);
        pastPurchase($this->customer, $this->cola, 2, 3);

        expect((float) sellItems([[$this->cola, 1]], $this->customer)->rules_discount_amount)->toBe(10.0)
            ->and((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0)
            ->and((float) sellItems([[$this->chips, 1]], $this->customer)->rules_discount_amount)->toBe(0.0);
    });

    it('combines the condition tree and the expression', function () {
        makeRule(['action_value' => 10, 'expression' => 'cart.subtotal > 150'])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.product_id', 'operator' => 'eq', 'value' => $this->cola->id],
        ]]]);

        expect((float) sellItems([[$this->cola, 2]])->rules_discount_amount)->toBe(20.0)
            ->and((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
    });

    it('treats a broken expression as no match and never throws', function () {
        makeRule(['expression' => 'this is not valid ((']);
        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);

        DiscountRule::query()->update(['expression' => 'customer.password == 1']);
        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
    });

    it('validates expressions and refuses anything outside the whitelist', function () {
        $expressions = app(RuleExpression::class);

        expect($expressions->validate('customer.orders_count >= 10 and cart.subtotal > 5'))->toBeNull()
            ->and($expressions->validate('customer_spent(90) > 500 and max(item.quantity, 2) > 1'))->toBeNull()
            ->and($expressions->validate('1 +'))->not->toBeNull()
            ->and($expressions->validate('unknown.value > 1'))->not->toBeNull()
            ->and($expressions->validate('customer.secret > 1'))->not->toBeNull()
            ->and($expressions->validate("constant('PHP_VERSION') == 1"))->not->toBeNull()
            ->and($expressions->validate('system("ls")'))->not->toBeNull()
            ->and($expressions->validate(str_repeat('1 + ', 200).'1'))->not->toBeNull();
    });
});

describe('stacking and priority', function () {
    it('compounds stackable rules on what is left', function () {
        makeRule(['name' => 'A', 'priority' => 1, 'action_value' => 10]);
        makeRule(['name' => 'B', 'priority' => 2, 'action_value' => 10]);

        $order = sellItems([[$this->cola, 1]]);

        expect((float) $order->rules_discount_amount)->toBe(19.0)
            ->and(DiscountApplication::pluck('rule_name')->all())->toBe(['A', 'B']);
    });

    it('stops lower-priority rules after a rule that cannot combine', function () {
        makeRule(['name' => 'Exclusive', 'priority' => 1, 'action_value' => 10, 'stackable' => false]);
        makeRule(['name' => 'Other', 'priority' => 2, 'action_value' => 50]);

        $order = sellItems([[$this->cola, 1]]);

        expect((float) $order->rules_discount_amount)->toBe(10.0)->and(DiscountApplication::count())->toBe(1);
    });

    it('lets a non-stackable rule keep its priority but only lock lines it discounted', function () {
        makeRule(['name' => 'Cola only', 'priority' => 1, 'action_value' => 10, 'stackable' => false])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.product_id', 'operator' => 'eq', 'value' => $this->cola->id],
        ]]]);
        makeRule(['name' => 'Everything', 'priority' => 2, 'action_value' => 50]);

        $order = sellItems([[$this->cola, 1], [$this->chips, 1]]);

        expect((float) $order->items->firstWhere('product_id', $this->cola->id)->discount_amount)->toBe(10.0)
            ->and((float) $order->items->firstWhere('product_id', $this->chips->id)->discount_amount)->toBe(25.0);
    });

    it('runs the lowest priority number first', function () {
        makeRule(['name' => 'Late', 'priority' => 9, 'action_value' => 50]);
        makeRule(['name' => 'Early', 'priority' => 1, 'action_value' => 10, 'stackable' => false]);

        sellItems([[$this->cola, 1]]);

        expect(DiscountApplication::pluck('rule_name')->all())->toBe(['Early']);
    });

    it('applies the manual discount after the rules', function () {
        makeRule(['action_value' => 20]);

        $order = sellItems([[$this->cola, 1]], null, ['discount_type' => 'percentage', 'discount_value' => 10]);

        expect((float) $order->rules_discount_amount)->toBe(20.0)
            ->and((float) $order->discount_amount)->toBe(28.0)
            ->and((float) $order->total)->toBe(72.0);
    });
});

describe('availability', function () {
    it('ignores inactive, not-yet-started and expired rules and other companies', function () {
        makeRule(['is_active' => false]);
        makeRule(['starts_at' => now()->addDay()]);
        makeRule(['ends_at' => now()->subDay()]);
        DiscountRule::factory()->create();

        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);

        makeRule(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(10.0);
    });
});

describe('coupons', function () {
    it('only applies a coupon rule when its code is entered', function () {
        makeRule(['name' => 'Spring', 'action_value' => 10, 'coupon_code' => 'SPRING']);

        expect((float) sellItems([[$this->cola, 1]])->rules_discount_amount)->toBe(0.0);

        $order = sellItems([[$this->cola, 1]], null, ['coupon' => 'spring']);
        expect((float) $order->rules_discount_amount)->toBe(10.0)
            ->and(DiscountApplication::firstOrFail()->coupon_code)->toBe('SPRING');
    });

    it('rejects an unknown coupon and a coupon that matches nothing in the cart', function () {
        makeRule(['coupon_code' => 'COLA'])->update(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.product_id', 'operator' => 'eq', 'value' => $this->cola->id],
        ]]]);

        expect(fn () => sellItems([[$this->cola, 1]], null, ['coupon' => 'WRONG']))->toThrow(InvalidArgumentException::class, 'not valid')
            ->and(fn () => sellItems([[$this->chips, 1]], null, ['coupon' => 'COLA']))->toThrow(InvalidArgumentException::class, 'does not apply');
    });

    it('enforces total and per-customer usage limits and frees voided uses', function () {
        makeRule(['coupon_code' => 'ONCE', 'usage_limit' => 2, 'per_customer_limit' => 1]);
        $other = Customer::create(['company_id' => $this->company->id, 'name' => 'Other']);

        $first = sellItems([[$this->cola, 1]], $this->customer, ['coupon' => 'ONCE']);
        expect(fn () => sellItems([[$this->cola, 1]], $this->customer, ['coupon' => 'ONCE']))->toThrow(InvalidArgumentException::class, 'usage limit')
            ->and(fn () => sellItems([[$this->cola, 1]], null, ['coupon' => 'ONCE']))->toThrow(InvalidArgumentException::class, 'usage limit');

        sellItems([[$this->cola, 1]], $other, ['coupon' => 'ONCE']);
        expect(fn () => sellItems([[$this->cola, 1]], Customer::create(['company_id' => $this->company->id, 'name' => 'Third']), ['coupon' => 'ONCE']))->toThrow(InvalidArgumentException::class, 'usage limit');

        $first->update(['status' => OrderStatus::Voided]);
        expect((float) sellItems([[$this->cola, 1]], Customer::create(['company_id' => $this->company->id, 'name' => 'Fourth']), ['coupon' => 'ONCE'])->rules_discount_amount)->toBe(10.0);
    });

    it('does not let coupon codes from other companies in', function () {
        DiscountRule::factory()->create(['coupon_code' => 'THEIRS']);

        expect(fn () => sellItems([[$this->cola, 1]], null, ['coupon' => 'THEIRS']))->toThrow(InvalidArgumentException::class);
    });
});

it('checks cash against the discounted total and records one application per line and rule', function () {
    makeRule(['action_value' => 50]);

    $order = app(CheckoutAction::class)->execute(
        branch: $this->branch,
        cartItems: [['product_id' => $this->cola->id, 'quantity' => 1], ['product_id' => $this->chips->id, 'quantity' => 1]],
        cashReceived: 75,
        cashier: $this->cashier,
    );

    expect((float) $order->total)->toBe(75.0)
        ->and(DiscountApplication::count())->toBe(2)
        ->and((float) OrderItem::sum('discount_amount'))->toBe(75.0);

    expect(fn () => app(CheckoutAction::class)->execute($this->branch, [['product_id' => $this->cola->id, 'quantity' => 1]], 49.0, $this->cashier))
        ->toThrow(InvalidArgumentException::class, 'Insufficient cash');
});
