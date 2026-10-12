<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['currency' => 'USD']);
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->location = Location::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
        'type' => 'branch',
    ]);
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $this->branch->id,
    ]);
    Permission::findOrCreate('access pos');
    $this->user->givePermissionTo('access pos');
    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'base_price' => 25.00,
        'cost_price' => 15.00,
        'is_active' => true,
    ]);

    // Add initial stock
    StockMovement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $this->product->id,
        'location_id' => $this->location->id,
        'quantity' => 100,
    ]);
});

it('requires authentication for checkout', function () {
    $this->postJson(route('pos.checkout'), [])
        ->assertUnauthorized();
});

it('validates checkout request', function () {
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items', 'cash_received']);
});

it('validates cart items structure', function () {
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['invalid' => 'data'],
            ],
            'cash_received' => 100,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.product_id', 'items.0.quantity']);
});

it('completes checkout successfully', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ],
            ],
            'cash_received' => 100.00,
        ])
        ->assertSuccessful();

    expect($response->json('success'))->toBeTrue();
    expect($response->json('order.total'))->toEqual(50.0);
    expect($response->json('order.change'))->toEqual(50.0);

    // Verify order was created
    $this->assertDatabaseHas('orders', [
        'branch_id' => $this->branch->id,
        'total' => 50.00,
        'status' => OrderStatus::Completed->value,
        'cashier_id' => $this->user->id,
    ]);

    // Verify stock was deducted
    $currentStock = StockMovement::where('product_id', $this->product->id)
        ->where('location_id', $this->location->id)
        ->sum('quantity');

    expect($currentStock)->toBe(98);
});

it('applies percentage discount correctly', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 4,
                    'unit_price' => 25.00,
                ],
            ],
            'cash_received' => 100.00,
            'discount_type' => 'percentage',
            'discount_value' => 10,
        ])
        ->assertSuccessful();

    // 4 * 25 = 100, 10% discount = 10, total = 90
    expect($response->json('order.total'))->toEqual(90.0);
    expect($response->json('order.discount_amount'))->toEqual(10.0);
});

it('applies fixed discount correctly', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 4,
                    'unit_price' => 25.00,
                ],
            ],
            'cash_received' => 100.00,
            'discount_type' => 'fixed',
            'discount_value' => 15,
        ])
        ->assertSuccessful();

    // 4 * 25 = 100, fixed discount = 15, total = 85
    expect($response->json('order.total'))->toEqual(85.0);
    expect($response->json('order.discount_amount'))->toEqual(15.0);
});

it('rejects checkout with insufficient stock', function () {
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 999,
                    'unit_price' => 25.00,
                ],
            ],
            'cash_received' => 99999.00,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('rejects checkout with insufficient cash', function () {
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'unit_price' => 25.00,
                ],
            ],
            'cash_received' => 10.00,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('generates unique order numbers', function () {
    // First order
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 25.00],
            ],
            'cash_received' => 50.00,
        ])
        ->assertSuccessful();

    // Second order
    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 25.00],
            ],
            'cash_received' => 50.00,
        ])
        ->assertSuccessful();

    $orders = Order::where('branch_id', $this->branch->id)->get();

    expect($orders)->toHaveCount(2);
    expect($orders[0]->order_number)->not->toBe($orders[1]->order_number);
});

it('returns receipt data for order', function () {
    // Create an order first
    $checkoutResponse = $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'unit_price' => 25.00],
            ],
            'cash_received' => 100.00,
        ])
        ->assertSuccessful();

    $orderId = $checkoutResponse->json('order.id');

    // Fetch receipt
    $this->actingAs($this->user)
        ->get(route('pos.receipt', $orderId))
        ->assertSuccessful()
        ->assertJsonStructure([
            'tenant_name',
            'branch_name',
            'order_number',
            'date',
            'time',
            'cashier',
            'items',
            'subtotal',
            'total',
            'cash_received',
            'change',
        ]);
});

it('prevents accessing receipt from another branch', function () {
    // Create order
    $checkoutResponse = $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 25.00],
            ],
            'cash_received' => 50.00,
        ])
        ->assertSuccessful();

    $orderId = $checkoutResponse->json('order.id');

    // Create another user in different branch
    $otherBranch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'branch_id' => $otherBranch->id,
    ]);

    // Try to access receipt from different branch
    $this->actingAs($otherUser)
        ->get(route('pos.receipt', $orderId))
        ->assertForbidden();
});

it('assigns a customer to a cash sale', function () {
    $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Rana']);

    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 25.00]],
            'cash_received' => 30.00,
            'payment_type' => 'cash',
            'customer_id' => $customer->id,
        ])
        ->assertSuccessful();

    $this->assertDatabaseHas('orders', [
        'customer_id' => $customer->id,
        'payment_type' => 'cash',
        'status' => OrderStatus::Completed->value,
        'debt_status' => null,
    ]);
});

it('does not accept a customer from another tenant on a cash sale', function () {
    $foreign = Customer::create(['tenant_id' => Tenant::factory()->create()->id, 'name' => 'Other']);

    $this->actingAs($this->user)
        ->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'cash_received' => 30.00,
            'customer_id' => $foreign->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');
});
