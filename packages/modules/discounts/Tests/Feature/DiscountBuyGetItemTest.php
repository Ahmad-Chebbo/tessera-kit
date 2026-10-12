<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Discounts\Services\DiscountPreview;
use App\Modules\Discounts\Templates\DiscountTemplates;
use App\Modules\Sales\Actions\CheckoutAction;
use App\Modules\Sales\Models\Order;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;

function bundleRule(array $config, array $attributes = []): DiscountRule
{
    return DiscountRule::factory()->create([
        'tenant_id' => test()->tenant->id,
        'name' => 'Burger deal',
        'action_type' => DiscountAction::BuyGetItem,
        'action_value' => 0,
        'action_config' => $config,
        ...$attributes,
    ]);
}

/**
 * @param  list<array{0: Product, 1: int|float}>  $items
 */
function sellBundle(array $items, ?string $coupon = null): Order
{
    $test = test();

    return app(CheckoutAction::class)->execute(
        branch: $test->branch,
        cartItems: array_map(fn (array $item): array => ['product_id' => $item[0]->id, 'quantity' => $item[1]], $items),
        cashReceived: 100000,
        cashier: $test->cashier,
        couponCode: $coupon,
    );
}

function previewBundle(array $items): array
{
    $test = test();

    return app(DiscountPreview::class)->run(
        $test->cashier,
        array_map(fn (array $item): array => ['product_id' => $item[0]->id, 'quantity' => $item[1]], $items),
    );
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);

    $make = fn (string $name, float $price): Product => Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => $name, 'base_price' => $price, 'unlimited_quantity' => true, 'is_active' => true]);
    $this->burger = $make('Burger', 100);
    $this->wrap = $make('Wrap', 80);
    $this->cola = $make('Cola', 30);
    $this->juice = $make('Juice', 20);
    $this->fries = $make('Fries', 40);
});

it('gives a different item free when enough of the chosen items are bought', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    $order = sellBundle([[$this->burger, 2], [$this->cola, 1]]);

    expect((float) $order->rules_discount_amount)->toBe(30.0)
        ->and((float) $order->total)->toBe(200.0)
        ->and((float) $order->items->firstWhere('product_id', $this->cola->id)->discount_amount)->toBe(30.0)
        ->and((float) $order->items->firstWhere('product_id', $this->burger->id)->discount_amount)->toBe(0.0)
        ->and(DiscountApplication::count())->toBe(1);
});

it('does nothing until the buy quantity is reached', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    expect((float) sellBundle([[$this->burger, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
});

it('repeats for every full set bought and never frees more than is in the cart', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    expect((float) sellBundle([[$this->burger, 6], [$this->cola, 3]])->rules_discount_amount)->toBe(90.0)
        ->and((float) sellBundle([[$this->burger, 6], [$this->cola, 2]])->rules_discount_amount)->toBe(60.0)
        ->and((float) sellBundle([[$this->burger, 5], [$this->cola, 5]])->rules_discount_amount)->toBe(60.0);
});

it('gives two free items per set', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 2]);

    expect((float) sellBundle([[$this->burger, 1], [$this->cola, 3]])->rules_discount_amount)->toBe(60.0);
});

it('counts several buy items together', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id, $this->wrap->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    expect((float) sellBundle([[$this->burger, 1], [$this->wrap, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(30.0);
});

it('frees the cheapest of the chosen reward items first', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id, $this->fries->id, $this->juice->id], 'buy_quantity' => 1, 'get_quantity' => 1]);

    $order = sellBundle([[$this->burger, 1], [$this->cola, 1], [$this->fries, 1]]);

    expect((float) $order->rules_discount_amount)->toBe(30.0)
        ->and((float) $order->items->firstWhere('product_id', $this->cola->id)->discount_amount)->toBe(30.0)
        ->and((float) $order->items->firstWhere('product_id', $this->fries->id)->discount_amount)->toBe(0.0);
});

it('can spread the free units over several reward items', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id, $this->juice->id], 'buy_quantity' => 1, 'get_quantity' => 2]);

    $order = sellBundle([[$this->burger, 1], [$this->cola, 1], [$this->juice, 1]]);

    expect((float) $order->rules_discount_amount)->toBe(50.0);
});

it('gives a percentage off instead of free', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 1, 'get_discount_percent' => 50]);

    expect((float) sellBundle([[$this->burger, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(15.0);
});

it('can limit how many times a deal applies in one order', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 1, 'max_sets' => 2]);

    expect((float) sellBundle([[$this->burger, 5], [$this->cola, 5]])->rules_discount_amount)->toBe(60.0);
});

it('needs an extra unit when the same item is both bought and given', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->burger->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    expect((float) sellBundle([[$this->burger, 2]])->rules_discount_amount)->toBe(0.0)
        ->and((float) sellBundle([[$this->burger, 3]])->rules_discount_amount)->toBe(100.0)
        ->and((float) sellBundle([[$this->burger, 6]])->rules_discount_amount)->toBe(200.0);
});

it('tells the till which free items the customer has earned but not added yet', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id, $this->juice->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    $preview = previewBundle([[$this->burger, 4]]);

    expect($preview['total'])->toBe(0.0)
        ->and($preview['suggestions'])->toHaveCount(1)
        ->and($preview['suggestions'][0]['quantity'])->toBe(2.0)
        ->and(collect($preview['suggestions'][0]['products'])->pluck('name')->sort()->values()->all())->toBe(['Cola', 'Juice'])
        ->and($preview['suggestions'][0]['rule_name'])->toBe('Burger deal');

    $partial = previewBundle([[$this->burger, 4], [$this->cola, 1]]);
    expect($partial['total'])->toBe(30.0)->and($partial['suggestions'][0]['quantity'])->toBe(1.0);

    $done = previewBundle([[$this->burger, 4], [$this->cola, 2]]);
    expect($done['suggestions'])->toBe([]);
});

it('gives no suggestion before the deal is earned', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1]);

    expect(previewBundle([[$this->burger, 1]])['suggestions'])->toBe([]);
});

it('works with conditions, priority and stacking like any other rule', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 1], ['priority' => 1, 'stackable' => false]);
    DiscountRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ten off', 'priority' => 2, 'action_value' => 10]);

    $order = sellBundle([[$this->burger, 1], [$this->cola, 1]]);

    expect((float) $order->items->firstWhere('product_id', $this->cola->id)->discount_amount)->toBe(30.0)
        ->and((float) $order->items->firstWhere('product_id', $this->burger->id)->discount_amount)->toBe(10.0);
});

it('only uses it with the coupon when the rule needs one', function () {
    bundleRule(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 1], ['coupon_code' => 'COMBO']);

    expect((float) sellBundle([[$this->burger, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(0.0)
        ->and((float) sellBundle([[$this->burger, 1], [$this->cola, 1]], 'combo')->rules_discount_amount)->toBe(30.0);
});

it('ignores a rule with missing settings', function () {
    bundleRule(['buy_product_ids' => [], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 1, 'get_quantity' => 1]);

    expect((float) sellBundle([[$this->burger, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(0.0);
});

describe('management', function () {
    beforeEach(function () {
        Permission::findOrCreate('manage discounts');
        $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id])->givePermissionTo('manage discounts');
    });

    $payload = fn (array $config = []) => [
        'name' => 'Burger + drink', 'is_active' => true, 'priority' => 10, 'stackable' => true,
        'conditions' => ['type' => 'group', 'logic' => 'all', 'children' => []],
        'action_type' => 'buy_get_item', 'action_value' => 0,
        'action_config' => [...['buy_product_ids' => [test()->burger->id], 'get_product_ids' => [test()->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1, 'max_sets' => 3, 'apply_to' => 'total', 'cap_amount' => null], ...$config],
    ];

    it('saves the chosen items and drops settings that do not belong to the action', function () use ($payload) {
        $this->actingAs($this->manager)->post(route('discounts.store'), $payload())->assertSessionHasNoErrors();

        $config = DiscountRule::firstOrFail()->action_config;
        expect($config)->toMatchArray(['buy_product_ids' => [$this->burger->id], 'get_product_ids' => [$this->cola->id], 'buy_quantity' => 2, 'get_quantity' => 1, 'max_sets' => 3])
            ->and($config)->not->toHaveKey('apply_to');
    });

    it('requires both item lists and quantities', function () use ($payload) {
        $this->actingAs($this->manager)->post(route('discounts.store'), $payload(['buy_product_ids' => [], 'get_product_ids' => null, 'buy_quantity' => null, 'get_quantity' => null]))
            ->assertSessionHasErrors(['action_config.buy_product_ids', 'action_config.get_product_ids', 'action_config.buy_quantity', 'action_config.get_quantity']);
    });

    it('rejects items from another tenant', function () use ($payload) {
        $foreign = Product::factory()->create();

        $this->actingAs($this->manager)->post(route('discounts.store'), $payload(['get_product_ids' => [$foreign->id]]))->assertSessionHasErrors('action_config.get_product_ids.0');
    });

    it('is offered as a ready-made template and validates its items', function () {
        expect(DiscountTemplates::exists('buy_get_item'))->toBeTrue();

        $this->actingAs($this->manager)->post(route('discounts.from-template', 'buy_get_item'), [
            'buy_products' => [$this->burger->id, $this->wrap->id], 'buy' => 2, 'get_products' => [$this->cola->id], 'get' => 1, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $rule = DiscountRule::firstOrFail();
        expect($rule->action_type)->toBe(DiscountAction::BuyGetItem)
            ->and($rule->name)->toBe('Buy 2, get 1 free item')
            ->and($rule->config('buy_product_ids'))->toBe([$this->burger->id, $this->wrap->id])
            ->and($rule->config('get_discount_percent'))->toEqual(100);

        expect((float) sellBundle([[$this->burger, 1], [$this->wrap, 1], [$this->cola, 1]])->rules_discount_amount)->toBe(30.0);

        $this->actingAs($this->manager)->post(route('discounts.from-template', 'buy_get_item'), ['buy_products' => [], 'buy' => 1, 'get_products' => [Product::factory()->create()->id], 'get' => 1])
            ->assertSessionHasErrors(['buy_products', 'get_products.0']);
    });
});
