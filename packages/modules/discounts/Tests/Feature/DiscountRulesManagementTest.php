<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerGroup;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Sales\Models\Order;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function rulePayload(array $overrides = []): array
{
    return [...[
        'name' => 'Loyal customers',
        'is_active' => true,
        'priority' => 10,
        'stackable' => true,
        'conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.bought_quantity', 'operator' => 'gte', 'value' => 10, 'params' => ['window_days' => 90]],
        ]],
        'expression' => '',
        'action_type' => 'percent',
        'action_value' => 15,
        'action_config' => ['cap_amount' => 50],
    ], ...$overrides];
}

beforeEach(function () {
    foreach (['manage discounts', 'edit customers', 'create customers'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id])->givePermissionTo(['manage discounts', 'edit customers', 'create customers']);

    $this->other = Tenant::factory()->create();
    $this->otherManager = User::factory()->create(['tenant_id' => $this->other->id])->givePermissionTo(['manage discounts', 'edit customers', 'create customers']);
});

describe('rules', function () {
    it('creates a rule with conditions, schedule, coupon and limits', function () {
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload([
            'starts_at' => '2026-10-01T00:00', 'ends_at' => '2026-12-31T23:59',
            'coupon_code' => ' spring-25 ', 'usage_limit' => 100, 'per_customer_limit' => 1,
        ]))->assertRedirect(route('discounts.index'));

        $rule = DiscountRule::firstOrFail();
        expect($rule->tenant_id)->toBe($this->tenant->id)
            ->and($rule->coupon_code)->toBe('SPRING-25')
            ->and($rule->conditions['children'][0]['params']['window_days'])->toBe(90)
            ->and($rule->config('cap_amount'))->toEqual(50)
            ->and($rule->usage_limit)->toBe(100);
    });

    it('drops action settings that do not belong to the chosen action', function () {
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload([
            'action_type' => 'buy_x_get_y', 'action_value' => 0,
            'action_config' => ['apply_to' => 'total', 'buy_quantity' => 10, 'get_quantity' => 1, 'cap_amount' => null],
        ]))->assertSessionHasNoErrors();

        expect(DiscountRule::firstOrFail()->action_config)->toBe(['buy_quantity' => 10, 'get_quantity' => 1]);
    });

    it('validates the basics', function () {
        $this->actingAs($this->manager)->post(route('discounts.store'), [
            'name' => '', 'priority' => 'x', 'action_type' => 'magic', 'action_value' => 150,
            'ends_at' => '2026-01-01', 'starts_at' => '2026-02-01',
        ])->assertSessionHasErrors(['name', 'priority', 'action_type', 'ends_at']);

        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['action_value' => 150]))->assertSessionHasErrors('action_value');
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['action_type' => 'buy_x_get_y', 'action_config' => ['buy_quantity' => null]]))
            ->assertSessionHasErrors(['action_config.buy_quantity', 'action_config.get_quantity']);
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['per_customer_limit' => 2]))->assertSessionHasErrors('per_customer_limit');
    });

    it('validates the condition tree', function () {
        $bad = fn (array $tree) => $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['conditions' => $tree]))->assertSessionHasErrors('conditions');

        $bad(['type' => 'group', 'logic' => 'all', 'children' => [['type' => 'condition', 'field' => 'made.up', 'operator' => 'eq', 'value' => 1]]]);
        $bad(['type' => 'group', 'logic' => 'all', 'children' => [['type' => 'condition', 'field' => 'item.quantity', 'operator' => 'in', 'value' => 1]]]);
        $bad(['type' => 'group', 'logic' => 'all', 'children' => [['type' => 'condition', 'field' => 'context.time', 'operator' => 'gte', 'value' => '25:00']]]);
        $bad(['type' => 'group', 'logic' => 'all', 'children' => [['type' => 'condition', 'field' => 'item.quantity', 'operator' => 'gt', 'value' => 1, 'params' => ['window_days' => 30]]]]);
        $bad(['type' => 'group', 'logic' => 'maybe', 'children' => []]);
        $bad(['type' => 'condition', 'field' => 'item.quantity', 'operator' => 'gt', 'value' => 1]);
        $bad(['type' => 'group', 'logic' => 'all', 'children' => [['type' => 'condition', 'field' => 'item.quantity', 'operator' => 'between', 'value' => [1]]]]);
    });

    it('only accepts records of the same tenant in conditions', function () {
        $foreign = Category::factory()->create(['tenant_id' => $this->other->id]);
        $own = Category::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.category_id', 'operator' => 'in', 'value' => [$foreign->id]],
        ]]]))->assertSessionHasErrors('conditions');

        $this->flushSession();
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['conditions' => ['type' => 'group', 'logic' => 'all', 'children' => [
            ['type' => 'condition', 'field' => 'item.category_id', 'operator' => 'in', 'value' => [$own->id]],
        ]]]))->assertSessionHasNoErrors();
    });

    it('validates the expression', function () {
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['expression' => 'customer.orders_count >=']))->assertSessionHasErrors('expression');
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['expression' => "constant('PHP_VERSION')"]))->assertSessionHasErrors('expression');
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['expression' => 'customer.orders_count >= 10 and customer_spent(90) > 100']))->assertSessionHasNoErrors();
    });

    it('keeps coupon codes unique inside a tenant only', function () {
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['coupon_code' => 'SAVE']))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('discounts.store'), rulePayload(['coupon_code' => 'save']))->assertSessionHasErrors('coupon_code');
        $this->actingAs($this->otherManager)->post(route('discounts.store'), rulePayload(['coupon_code' => 'SAVE']))->assertSessionHasNoErrors();
    });

    it('updates, pauses, duplicates and deletes a rule', function () {
        $rule = DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'coupon_code' => 'KEEP', 'name' => 'Base']);

        $this->actingAs($this->manager)->put(route('discounts.update', $rule), rulePayload(['name' => 'Renamed', 'coupon_code' => 'KEEP']))->assertRedirect(route('discounts.index'));
        expect($rule->fresh()->name)->toBe('Renamed');

        $this->actingAs($this->manager)->post(route('discounts.toggle', $rule))->assertRedirect();
        expect($rule->fresh()->is_active)->toBeFalse();

        $this->actingAs($this->manager)->post(route('discounts.duplicate', $rule))->assertRedirect();
        $copy = DiscountRule::where('id', '!=', $rule->id)->firstOrFail();
        expect($copy->name)->toBe('Renamed (copy)')->and($copy->is_active)->toBeFalse()->and($copy->coupon_code)->toBeNull();

        $this->actingAs($this->manager)->delete(route('discounts.destroy', $rule))->assertRedirect();
        expect(DiscountRule::find($rule->id))->toBeNull();
    });

    it('keeps rules private to their tenant', function () {
        $theirs = DiscountRule::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->manager)->get(route('discounts.edit', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->put(route('discounts.update', $theirs), rulePayload())->assertForbidden();
        $this->actingAs($this->manager)->delete(route('discounts.destroy', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->post(route('discounts.toggle', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->post(route('discounts.duplicate', $theirs))->assertForbidden();
    });

    it('requires the manage discounts permission and the discounts module', function () {
        $clerk = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($clerk)->get(route('discounts.index'))->assertForbidden();

        $plan = Plan::factory()->withModules([Module::Pos])->create();
        $this->tenant->subscriptions()->delete();
        Subscription::create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay()]);

        $this->actingAs($this->manager)->get(route('discounts.index'))->assertForbidden();
    });

    it('lists rules with status and usage, ignoring voided orders', function () {
        $rule = DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Busy']);
        DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old', 'ends_at' => now()->subDay()]);
        DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

        foreach (['completed', 'completed', 'voided'] as $i => $status) {
            $order = Order::create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'order_number' => "T-$i", 'subtotal' => 100, 'total' => 90, 'status' => $status, 'cashier_id' => $this->manager->id, 'payment_type' => 'cash']);
            DiscountApplication::create(['order_id' => $order->id, 'discount_rule_id' => $rule->id, 'rule_name' => 'Busy', 'amount' => 10]);
        }

        $this->actingAs($this->manager)->get(route('discounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discounts/Index')
                ->has('rules', 3)
                ->where('rules.0.name', 'Busy')
                ->where('rules.0.uses', 2)
                ->where('rules.0.total_discounted', 20)
                ->where('rules.0.status', 'active'));
    });

    it('offers the builder its facts and tenant records', function () {
        Category::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Drinks']);
        Category::factory()->create(['tenant_id' => $this->other->id, 'name' => 'Hidden']);

        $this->actingAs($this->manager)->get(route('discounts.create'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Discounts/Edit')
                ->where('rule', null)
                ->where('catalogue.facts', fn ($facts) => collect($facts)->contains('key', 'customer.orders_count'))
                ->where('catalogue.lookups', fn ($lookups) => count($lookups['item.category_id'] ?? []) === 1));
    });
});

describe('simulator', function () {
    it('runs rules on a cart without saving anything', function () {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 100, 'is_active' => true]);
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Sim']);
        DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'action_value' => 10, 'coupon_code' => 'TRY']);

        $this->actingAs($this->manager)->postJson(route('discounts.simulate'), [
            'items' => [['product_id' => $product->id, 'quantity' => 3]], 'customer_id' => $customer->id, 'coupon_code' => 'try',
        ])->assertOk()
            ->assertJsonPath('subtotal', 300)
            ->assertJsonPath('total', 30)
            ->assertJsonPath('coupon_status', 'applied')
            ->assertJsonPath('lines.0.product_id', $product->id)
            ->assertJsonPath('lines.0.applied.0.rule_name', DiscountRule::first()->name);

        expect(Order::count())->toBe(0)->and(DiscountApplication::count())->toBe(0);
    });

    it('rejects products and customers from other tenants', function () {
        $foreign = Product::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->manager)->postJson(route('discounts.simulate'), ['items' => [['product_id' => $foreign->id, 'quantity' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.product_id');
    });

    it('shows the simulator page', function () {
        $this->actingAs($this->manager)->get(route('discounts.simulator'))->assertInertia(fn (Assert $page) => $page->component('Discounts/Simulator'));
    });
});

describe('customer groups', function () {
    it('creates, renames and deletes groups and lets customers join one', function () {
        $this->actingAs($this->manager)->post(route('customer-groups.store'), ['name' => 'VIP'])->assertRedirect();
        $group = CustomerGroup::firstOrFail();

        $this->actingAs($this->manager)->post(route('customer-groups.store'), ['name' => 'VIP'])->assertSessionHasErrors('name');
        $this->actingAs($this->otherManager)->post(route('customer-groups.store'), ['name' => 'VIP'])->assertSessionHasNoErrors();

        $this->actingAs($this->manager)->put(route('customer-groups.update', $group), ['name' => 'Gold'])->assertRedirect();
        expect($group->fresh()->name)->toBe('Gold');

        $this->actingAs($this->manager)->post(route('customers.store'), ['name' => 'Rana', 'customer_group_id' => $group->id, 'is_active' => true])->assertSessionHasNoErrors();
        expect(Customer::firstOrFail()->customer_group_id)->toBe($group->id);

        $foreign = CustomerGroup::where('tenant_id', $this->other->id)->firstOrFail();
        $this->actingAs($this->manager)->post(route('customers.store'), ['name' => 'Bad', 'customer_group_id' => $foreign->id])->assertSessionHasErrors('customer_group_id');
        $this->actingAs($this->manager)->delete(route('customer-groups.destroy', $foreign))->assertForbidden();

        $this->actingAs($this->manager)->delete(route('customer-groups.destroy', $group))->assertRedirect();
        expect(Customer::firstOrFail()->customer_group_id)->toBeNull();
    });

    it('lists groups with their customer counts', function () {
        $group = CustomerGroup::factory()->create(['tenant_id' => $this->tenant->id]);
        Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'A', 'customer_group_id' => $group->id]);

        $this->actingAs($this->manager)->get(route('customer-groups.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Customers/Groups')->where('groups.0.customers_count', 1));
    });
});
