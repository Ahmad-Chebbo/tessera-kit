<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Unit;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Models\HeldOrder;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

function grant(User $user, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return $user->givePermissionTo($permissions);
}

function stockOf(Product $product, Location $location): float
{
    return (float) StockMovement::where('product_id', $product->id)->where('location_id', $location->id)->sum('quantity');
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['currency' => 'USD']);
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
    grant($this->cashier, ['access pos']);

    $this->category = Category::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id, 'base_price' => 100, 'sale_price' => null, 'currency' => 'USD']);
    StockMovement::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id, 'quantity' => 10]);

    $this->other = Tenant::factory()->create(['currency' => 'USD']);
    $this->otherBranch = Branch::factory()->create(['tenant_id' => $this->other->id]);
    $this->otherLocation = Location::factory()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id, 'type' => 'branch']);
    $this->otherCategory = Category::factory()->create(['tenant_id' => $this->other->id]);
    $this->otherProduct = Product::factory()->create(['tenant_id' => $this->other->id, 'category_id' => $this->otherCategory->id, 'base_price' => 100]);
    StockMovement::factory()->create(['tenant_id' => $this->other->id, 'product_id' => $this->otherProduct->id, 'location_id' => $this->location->id, 'quantity' => 10]);
    $this->otherCustomer = Customer::create(['tenant_id' => $this->other->id, 'name' => 'Rival customer', 'is_active' => true]);
    $this->customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'My customer', 'is_active' => true]);
});

function checkout($test, array $overrides = [], ?User $as = null)
{
    $payload = array_merge([
        'items' => [['product_id' => $test->product->id, 'quantity' => 1]],
        'cash_received' => 1000,
    ], $overrides);

    return $test->actingAs($as ?? $test->cashier)->postJson(route('pos.checkout'), $payload);
}

describe('checkout', function () {
    it('rejects a product from another tenant', function () {
        checkout($this, ['items' => [['product_id' => $this->otherProduct->id, 'quantity' => 1]]])->assertUnprocessable();

        expect(Order::count())->toBe(0);
    });

    it('rejects a customer from another tenant', function () {
        checkout($this, ['customer_id' => $this->otherCustomer->id, 'payment_type' => 'debt'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    });

    it('rejects a percentage discount above 100', function () {
        checkout($this, ['discount_type' => 'percentage', 'discount_value' => 150])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('discount_value');

        expect(Order::count())->toBe(0);
    });

    it('requires a customer for credit sales', function () {
        checkout($this, ['payment_type' => 'debt'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('customer_id');
    });

    it('accepts fractional quantities for weighed goods', function () {
        checkout($this, ['items' => [['product_id' => $this->product->id, 'quantity' => 0.5]]])
            ->assertSuccessful()
            ->assertJsonPath('order.total', 50);
    });

    it('does not let the client undercut the server price', function () {
        checkout($this, ['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 0.01]]])
            ->assertUnprocessable();

        expect(Order::count())->toBe(0);
    });

    it('tolerates tiny exchange-rate rounding differences in the client price', function () {
        checkout($this, ['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_price' => 100.4]]])
            ->assertSuccessful();
    });

    it('uses the server price when the client sends none', function () {
        checkout($this)->assertSuccessful()->assertJsonPath('order.total', 100);
    });

    it('rejects a unit that does not belong to the product', function () {
        $unit = Unit::create(['tenant_id' => $this->tenant->id, 'name' => 'Box', 'abbreviation' => 'bx', 'is_active' => true]);

        checkout($this, ['items' => [['product_id' => $this->product->id, 'quantity' => 1, 'unit_id' => $unit->id]]])
            ->assertUnprocessable();

        expect(Order::count())->toBe(0);
    });
});

describe('offline sync', function () {
    $sale = fn ($test, array $extra = []) => array_merge([
        'offline_id' => 'offline-1',
        'items' => [['product_id' => $test->product->id, 'quantity' => 1]],
        'cash_received' => 1000,
    ], $extra);

    it('is idempotent per offline id so retries never double-charge or double-deduct', function () use ($sale) {
        $payload = ['sales' => [$sale($this)]];

        $first = $this->actingAs($this->cashier)->postJson(route('pos.sync'), $payload)->assertSuccessful();
        $second = $this->actingAs($this->cashier)->postJson(route('pos.sync'), $payload)->assertSuccessful();

        expect(Order::count())->toBe(1)
            ->and($second->json('results.0.success'))->toBeTrue()
            ->and($second->json('results.0.order_number'))->toBe($first->json('results.0.order_number'))
            ->and(stockOf($this->product, $this->location))->toBe(9.0);
    });

    it('reports a malformed sale without failing the whole batch', function () use ($sale) {
        $response = $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => [
            ['offline_id' => 'broken'],
            $sale($this, ['offline_id' => 'good']),
        ]])->assertSuccessful();

        expect($response->json('results.0.success'))->toBeFalse()
            ->and($response->json('results.1.success'))->toBeTrue()
            ->and(Order::count())->toBe(1);
    });

    it('refuses oversized batches', function () use ($sale) {
        $sales = array_map(fn ($i) => $sale($this, ['offline_id' => "bulk-{$i}"]), range(1, 51));

        $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => $sales])->assertUnprocessable();
    });

    it('cannot sell another tenant product', function () use ($sale) {
        $response = $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => [
            $sale($this, ['items' => [['product_id' => $this->otherProduct->id, 'quantity' => 1]]]),
        ]])->assertSuccessful();

        expect($response->json('results.0.success'))->toBeFalse()
            ->and(Order::count())->toBe(0);
    });

    it('keeps the customer and credit payment of an offline sale', function () use ($sale) {
        $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => [
            $sale($this, ['payment_type' => 'debt', 'customer_id' => $this->customer->id, 'cash_received' => 0]),
        ]])->assertSuccessful();

        expect(Order::first())->payment_type->toBe('debt')->customer_id->toBe($this->customer->id);
    });
});

describe('held orders', function () {
    it('can only be retrieved or deleted by the cashier who held them', function () {
        $colleague = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
        grant($colleague, ['access pos']);

        $held = HeldOrder::create([
            'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'cashier_id' => $this->cashier->id,
            'name' => 'Mine', 'items' => [], 'total' => 5,
        ]);

        $this->actingAs($colleague)->getJson(route('pos.retrieve-held-order', $held))->assertForbidden();
        $this->actingAs($colleague)->deleteJson(route('pos.delete-held-order', $held))->assertForbidden();
        $this->actingAs($this->cashier)->getJson(route('pos.retrieve-held-order', $held))->assertSuccessful();
    });
});

describe('purchases', function () {
    beforeEach(function () {
        $this->buyer = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]), ['create purchases', 'receive purchases']);
        $this->supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Mine', 'is_active' => true]);
        $this->otherSupplier = Supplier::create(['tenant_id' => $this->other->id, 'name' => 'Theirs', 'is_active' => true]);
    });

    $purchase = fn ($test, array $overrides = []) => array_merge([
        'supplier_id' => $test->supplier->id,
        'location_id' => $test->location->id,
        'currency' => 'USD',
        'items' => [['product_id' => $test->product->id, 'quantity' => 5, 'unit_cost' => 10]],
    ], $overrides);

    it('rejects a supplier, location or product from another tenant', function () use ($purchase) {
        $this->actingAs($this->buyer)->post(route('purchases.store'), $purchase($this, ['supplier_id' => $this->otherSupplier->id]))->assertSessionHasErrors('supplier_id');
        $this->actingAs($this->buyer)->post(route('purchases.store'), $purchase($this, ['location_id' => $this->otherLocation->id]))->assertSessionHasErrors('location_id');
        $this->actingAs($this->buyer)->post(route('purchases.store'), $purchase($this, ['items' => [['product_id' => $this->otherProduct->id, 'quantity' => 1, 'unit_cost' => 1]]]))->assertSessionHasErrors('items.0.product_id');

        expect(Purchase::count())->toBe(0);
    });

    it('adds stock only once however often receive is submitted', function () use ($purchase) {
        $this->actingAs($this->buyer)->post(route('purchases.store'), $purchase($this))->assertRedirect();
        $order = Purchase::first();

        $this->actingAs($this->buyer)->post(route('purchases.receive', $order));
        $this->actingAs($this->buyer)->post(route('purchases.receive', $order));

        expect(stockOf($this->product, $this->location))->toBe(15.0);
    });

    it('does not let another tenant receive the purchase', function () use ($purchase) {
        $this->actingAs($this->buyer)->post(route('purchases.store'), $purchase($this));
        $intruder = grant(User::factory()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id]), ['receive purchases']);

        $this->actingAs($intruder)->post(route('purchases.receive', Purchase::first()))->assertForbidden();
    });
});

describe('voiding orders', function () {
    beforeEach(function () {
        $this->manager = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]), ['void orders', 'view orders']);
    });

    it('puts the sold stock back exactly once', function () {
        checkout($this, ['items' => [['product_id' => $this->product->id, 'quantity' => 3]]])->assertSuccessful();
        $order = Order::first();
        expect(stockOf($this->product, $this->location))->toBe(7.0);

        $this->actingAs($this->manager)->post(route('orders.void', $order));
        $this->actingAs($this->manager)->post(route('orders.void', $order));

        expect($order->fresh()->status)->toBe(OrderStatus::Voided)
            ->and(stockOf($this->product, $this->location))->toBe(10.0);
    });

    it('also returns stock for voided credit sales', function () {
        checkout($this, ['payment_type' => 'debt', 'customer_id' => $this->customer->id, 'cash_received' => 0])->assertSuccessful();

        $this->actingAs($this->manager)->post(route('orders.void', Order::first()));

        expect(stockOf($this->product, $this->location))->toBe(10.0);
    });

    it('keeps a manager from voiding another branch order without the all-branches permission', function () {
        $branch2 = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $elsewhere = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branch2->id]), ['void orders']);
        checkout($this)->assertSuccessful();

        $this->actingAs($elsewhere)->post(route('orders.void', Order::first()))->assertForbidden();

        expect(Order::first()->status)->toBe(OrderStatus::Completed);
    });
});

describe('uploads', function () {
    beforeEach(function () {
        $this->editor = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]), ['create products', 'edit products', 'manage settings']);
        $this->productData = fn (array $extra = []) => array_merge([
            'name' => 'Upload test', 'base_price' => 5, 'currency' => 'USD', 'is_active' => true, 'unlimited_quantity' => false,
        ], $extra);
    });

    it('accepts a normal product image', function () {
        $this->actingAs($this->editor)->post(route('products.store'), ($this->productData)(['image' => UploadedFile::fake()->image('p.png', 200, 200)]))->assertSessionHasNoErrors();
    });

    it('rejects scripts, svg and oversized files as a product image', function (string $name, string $mime, int $kb) {
        $this->actingAs($this->editor)
            ->post(route('products.store'), ($this->productData)(['image' => UploadedFile::fake()->create($name, $kb, $mime)]))
            ->assertSessionHasErrors('image');

        expect(Product::where('name', 'Upload test')->exists())->toBeFalse();
    })->with([
        'php script' => ['shell.php', 'application/x-php', 5],
        'svg' => ['x.svg', 'image/svg+xml', 5],
        'huge png' => ['big.png', 'image/png', 6000],
    ]);

    it('applies the same rules to the tenant logo', function () {
        $this->actingAs($this->editor)->put(route('settings.update'), [
            'name' => 'Co', 'currency' => 'USD', 'logo' => UploadedFile::fake()->create('logo.php', 5, 'application/x-php'),
        ])->assertSessionHasErrors('logo');
    });
});

describe('catalogue references', function () {
    it('rejects a category from another tenant on products and as a parent category', function () {
        $editor = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]), ['create products', 'create categories']);

        $this->actingAs($editor)->post(route('products.store'), [
            'name' => 'X', 'base_price' => 1, 'currency' => 'USD', 'is_active' => true, 'unlimited_quantity' => false,
            'category_id' => $this->otherCategory->id,
        ])->assertSessionHasErrors('category_id');

        $this->actingAs($editor)->post(route('categories.store'), [
            'name' => 'Child', 'parent_id' => $this->otherCategory->id, 'is_active' => true,
        ])->assertSessionHasErrors('parent_id');
    });
});

describe('dashboard performance', function () {
    it('finds low stock products without querying once per product', function () {
        $viewer = grant(User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]), ['view dashboard']);
        Product::factory()->count(12)->create(['tenant_id' => $this->tenant->id, 'category_id' => $this->category->id]);

        DB::enableQueryLog();
        $response = $this->actingAs($viewer)->get(route('dashboard'));
        $stockQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'stock_movements'))->count();
        DB::disableQueryLog();

        $response->assertSuccessful()->assertInertia(fn ($page) => $page->where('widgets.0.key', 'low-stock')->has('widgets.0.data', 10));
        expect($stockQueries)->toBeLessThanOrEqual(2);
    });
});
