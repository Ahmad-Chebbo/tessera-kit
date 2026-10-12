<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Discounts\Engine\ConditionTreeValidator;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Discounts\Templates\DiscountTemplates;
use App\Modules\Sales\Actions\CheckoutAction;
use App\Modules\Sales\Models\Order;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function templateValues(string $key, object $test): array
{
    $values = [
        'percent' => 10, 'amount' => 10, 'threshold' => 100, 'quantity' => 5, 'buy' => 10, 'get' => 1, 'orders' => 5, 'purchases' => 5,
        'price' => 80, 'from' => '16:00', 'to' => '18:00', 'code' => 'WELCOME', 'days' => 30,
        'category' => $test->category->id, 'group' => $test->group->id, 'product' => $test->product->id,
        'buy_products' => [$test->product->id], 'get_products' => [$test->product->id],
    ];

    return collect(DiscountTemplates::definitions()[$key]['params'])->mapWithKeys(fn (array $param): array => [$param['name'] => $values[$param['name']]])->all();
}

function sellTemplate(object $test, int $quantity = 1, ?Customer $customer = null, ?string $coupon = null, string $paymentType = 'cash'): Order
{
    return app(CheckoutAction::class)->execute(
        branch: $test->branch, cartItems: [['product_id' => $test->product->id, 'quantity' => $quantity]], cashReceived: 100000,
        cashier: $test->user, customerId: $customer?->id, paymentType: $paymentType, couponCode: $coupon,
    );
}

beforeEach(function () {
    Permission::findOrCreate('manage discounts');
    $this->company = Company::factory()->create(['timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id])->givePermissionTo('manage discounts');
    $this->category = Category::factory()->create(['company_id' => $this->company->id]);
    $this->group = CustomerGroup::factory()->create(['company_id' => $this->company->id]);
    $this->product = Product::factory()->create(['company_id' => $this->company->id, 'category_id' => $this->category->id, 'base_price' => 100, 'unlimited_quantity' => true, 'is_active' => true]);
    $this->customer = Customer::create(['company_id' => $this->company->id, 'name' => 'Rana']);
});

it('produces a valid rule from every template', function (string $key) {
    $values = templateValues($key, $this);
    $rules = DiscountTemplates::rules($key, $this->company->id);

    expect(validator($values, $rules)->errors()->all())->toBe([]);

    $attributes = DiscountTemplates::build($key, $values);
    expect($attributes['name'])->not->toBeEmpty()
        ->and(app(ConditionTreeValidator::class)->errors($attributes['conditions'], $this->company->id))->toBe([]);
})->with(array_keys(DiscountTemplates::definitions()));

it('adds a template through the endpoint and lists it', function () {
    $this->actingAs($this->user)->post(route('discounts.from-template', 'bulk'), ['quantity' => 10, 'percent' => 15, 'is_active' => true])
        ->assertRedirect(route('discounts.index'));

    $rule = DiscountRule::firstOrFail();
    expect($rule->company_id)->toBe($this->company->id)->and($rule->name)->toBe('Bulk discount (10+)')->and($rule->is_active)->toBeTrue()->and($rule->action_value)->toBe(15.0);

    $this->actingAs($this->user)->get(route('discounts.index'))
        ->assertInertia(fn (Assert $page) => $page->has('templates', count(DiscountTemplates::definitions()))->has('rules', 1)->has('lookups.category', 1));
});

it('can add a template paused', function () {
    $this->actingAs($this->user)->post(route('discounts.from-template', 'cash'), ['percent' => 3, 'is_active' => false])->assertRedirect();

    expect(DiscountRule::firstOrFail()->is_active)->toBeFalse();
});

it('validates template values and the records they point at', function () {
    $other = Company::factory()->create();
    $foreign = Category::factory()->create(['company_id' => $other->id]);

    $this->actingAs($this->user)->post(route('discounts.from-template', 'category'), ['category' => $foreign->id, 'percent' => 10])->assertSessionHasErrors('category');
    $this->actingAs($this->user)->post(route('discounts.from-template', 'happy_hour'), ['percent' => 150, 'from' => '4pm', 'to' => '18:00'])->assertSessionHasErrors(['percent', 'from']);
    $this->actingAs($this->user)->post(route('discounts.from-template', 'coupon'), ['code' => 'bad code!', 'percent' => 10])->assertSessionHasErrors('code');

    DiscountRule::factory()->create(['company_id' => $this->company->id, 'coupon_code' => 'TAKEN']);
    $this->actingAs($this->user)->post(route('discounts.from-template', 'coupon'), ['code' => 'TAKEN', 'percent' => 10])->assertSessionHasErrors('code');
    $this->actingAs($this->user)->post(route('discounts.from-template', 'nonexistent'), [])->assertNotFound();
});

it('needs the manage discounts permission', function () {
    $clerk = User::factory()->create(['company_id' => $this->company->id]);

    $this->actingAs($clerk)->post(route('discounts.from-template', 'cash'), ['percent' => 3])->assertForbidden();
});

describe('templates behave as described at the till', function () {
    $add = fn (string $key, array $values = []) => DiscountRule::create([...DiscountTemplates::build($key, [...templateValues($key, test()), ...$values]), 'company_id' => test()->company->id, 'is_active' => true]);

    it('happy hour discounts only inside the window', function () use ($add) {
        $add('happy_hour');

        test()->travelTo(CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'));
        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(10.0);

        test()->travelTo(CarbonImmutable::parse('2026-10-14 19:00:00', 'UTC'));
        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(0.0);
    });

    it('bulk and buy X get Y reward quantity', function () use ($add) {
        $add('bulk');
        expect((float) sellTemplate($this, 4)->rules_discount_amount)->toBe(0.0)
            ->and((float) sellTemplate($this, 5)->rules_discount_amount)->toBe(50.0);

        DiscountRule::query()->delete();
        $add('buy_x_get_y');
        expect((float) sellTemplate($this, 11)->rules_discount_amount)->toBe(100.0);
    });

    it('spend thresholds work for percentages and fixed amounts', function () use ($add) {
        $add('spend_percent', ['threshold' => 200, 'percent' => 10]);
        expect((float) sellTemplate($this, 1)->rules_discount_amount)->toBe(0.0)
            ->and((float) sellTemplate($this, 2)->rules_discount_amount)->toBe(20.0);

        DiscountRule::query()->delete();
        $add('spend_amount', ['threshold' => 200, 'amount' => 25]);
        expect((float) sellTemplate($this, 2)->rules_discount_amount)->toBe(25.0);
    });

    it('welcome applies to the first order of a registered customer only', function () use ($add) {
        $add('welcome');

        expect((float) sellTemplate($this, 1)->rules_discount_amount)->toBe(0.0)
            ->and((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(10.0)
            ->and((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(0.0);
    });

    it('loyalty and repeat buyer need history', function () use ($add) {
        $add('loyalty', ['orders' => 2, 'percent' => 5]);

        expect((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(0.0);
        sellTemplate($this, 1, $this->customer);
        expect((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(5.0);

        DiscountRule::query()->delete();
        $add('repeat_buyer', ['purchases' => 3, 'percent' => 20]);
        expect((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(20.0);
    });

    it('category, group, special price, cash and weekend rules match what they say', function () use ($add) {
        $member = Customer::create(['company_id' => test()->company->id, 'name' => 'VIP', 'customer_group_id' => test()->group->id]);

        $add('category', ['percent' => 15]);
        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(15.0);

        DiscountRule::query()->delete();
        $add('group', ['percent' => 10]);
        expect((float) sellTemplate($this, 1, $member)->rules_discount_amount)->toBe(10.0)->and((float) sellTemplate($this, 1, $this->customer)->rules_discount_amount)->toBe(0.0);

        DiscountRule::query()->delete();
        $add('special_price', ['price' => 80]);
        expect((float) sellTemplate($this, 2)->rules_discount_amount)->toBe(40.0);

        DiscountRule::query()->delete();
        $add('cash', ['percent' => 5]);
        expect((float) sellTemplate($this, 1)->rules_discount_amount)->toBe(5.0)->and((float) sellTemplate($this, 1, $this->customer, null, 'debt')->rules_discount_amount)->toBe(0.0);

        DiscountRule::query()->delete();
        $add('weekend', ['percent' => 10]);
        test()->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00', 'UTC'));
        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(10.0);
        test()->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));
        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(0.0);
    });

    it('a coupon template only applies with its code', function () use ($add) {
        $add('coupon', ['code' => 'welcome', 'percent' => 10]);

        expect((float) sellTemplate($this)->rules_discount_amount)->toBe(0.0)
            ->and((float) sellTemplate($this, 1, null, 'WELCOME')->rules_discount_amount)->toBe(10.0);
    });
});
