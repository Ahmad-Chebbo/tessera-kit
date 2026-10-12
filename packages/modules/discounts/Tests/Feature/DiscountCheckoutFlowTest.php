<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Customers\Models\Customer;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Sales\Models\Order;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['access pos', 'view orders', 'view all orders'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->company = Company::factory()->create();
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->cashier = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id])->givePermissionTo(['access pos', 'view orders', 'view all orders']);
    $this->product = Product::factory()->create(['company_id' => $this->company->id, 'base_price' => 100, 'unlimited_quantity' => true, 'is_active' => true]);
    $this->rule = DiscountRule::factory()->create(['company_id' => $this->company->id, 'name' => 'Ten off', 'action_value' => 10]);
});

it('previews the discounts of a cart for the till', function () {
    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
    ])->assertOk()
        ->assertJsonPath('subtotal', 200)
        ->assertJsonPath('total', 20)
        ->assertJsonPath('lines.0.net', 180)
        ->assertJsonPath('lines.0.applied.0.rule_name', 'Ten off');
});

it('previews an empty cart, a coupon status and a customer', function () {
    DiscountRule::factory()->create(['company_id' => $this->company->id, 'coupon_code' => 'EXTRA', 'action_value' => 5]);
    $customer = Customer::create(['company_id' => $this->company->id, 'name' => 'Rana']);

    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), ['items' => []])->assertOk()->assertJsonPath('total', 0);

    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'customer_id' => $customer->id, 'coupon_code' => 'extra',
    ])->assertOk()->assertJsonPath('coupon_status', 'applied');

    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'coupon_code' => 'nope',
    ])->assertOk()->assertJsonPath('coupon_status', 'unknown');
});

it('does not preview products or customers of another company', function () {
    $foreign = Product::factory()->create();

    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), ['items' => [['product_id' => $foreign->id, 'quantity' => 1]]])
        ->assertUnprocessable();
});

it('requires the point of sale permission to preview', function () {
    $clerk = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);

    $this->actingAs($clerk)->postJson(route('pos.discounts.preview'), ['items' => []])->assertForbidden();
});

it('checks out with the rule discount and the coupon from the till', function () {
    DiscountRule::factory()->create(['company_id' => $this->company->id, 'name' => 'Coupon', 'coupon_code' => 'SAVE5', 'action_value' => 5]);

    $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 200, 'coupon_code' => 'save5',
    ])->assertOk()
        ->assertJsonPath('order.discount_amount', 14.5)
        ->assertJsonPath('order.total', 85.5);

    expect(DiscountApplication::count())->toBe(2);
});

it('rejects checkout with an invalid coupon without creating an order', function () {
    $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 200, 'coupon_code' => 'FAKE',
    ])->assertUnprocessable()->assertJsonPath('error', 'This coupon code is not valid.');

    expect(Order::count())->toBe(0);
});

it('applies rules and coupons to offline sales when they sync', function () {
    DiscountRule::factory()->create(['company_id' => $this->company->id, 'coupon_code' => 'OFF1', 'action_value' => 5]);

    $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => [[
        'offline_id' => 'sale-9', 'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 200, 'coupon_code' => 'OFF1',
    ]]])->assertOk()->assertJsonPath('synced', 1);

    expect((float) Order::firstOrFail()->rules_discount_amount)->toBe(14.5);
});

it('stops applying discounts when the plan no longer includes the module', function () {
    $plan = Plan::factory()->withModules([Module::Pos])->create();
    $this->company->subscriptions()->delete();
    Subscription::create(['company_id' => $this->company->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay()]);

    $this->actingAs($this->cashier)->postJson(route('pos.discounts.preview'), ['items' => [['product_id' => $this->product->id, 'quantity' => 1]]])
        ->assertOk()->assertJsonPath('total', 0);

    $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 200,
    ])->assertOk()->assertJsonPath('order.total', 100);

    $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 200, 'coupon_code' => 'ANY',
    ])->assertUnprocessable();
});

it('shows which rules discounted an order', function () {
    $response = $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $this->product->id, 'quantity' => 2]], 'cash_received' => 500,
    ])->assertOk();

    $this->actingAs($this->cashier)->get(route('orders.show', $response->json('order.id')))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Show')
            ->where('appliedDiscounts.0.rule_name', 'Ten off')
            ->where('appliedDiscounts.0.amount', 20));
});
