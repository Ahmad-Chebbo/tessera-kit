<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
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
    $this->category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);
});

it('requires authentication to access POS', function () {
    $this->get(route('pos.index'))
        ->assertRedirect(route('login'));
});

it('displays POS page for authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('pos.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('POS/Index')
            ->has('branch')
            ->has('categories')
            ->has('currency')
        );
});

it('returns products with stock levels', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'is_active' => true,
        'base_price' => 10.00,
    ]);

    // Add stock
    StockMovement::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_id' => $product->id,
        'location_id' => $this->location->id,
        'quantity' => 50,
    ]);

    $this->actingAs($this->user)
        ->get(route('pos.products'))
        ->assertSuccessful()
        ->assertJsonStructure([
            'products' => [
                '*' => [
                    'id',
                    'name',
                    'price',
                    'stock',
                    'category_id',
                ],
            ],
        ])
        ->assertJsonPath('products.0.stock', 50);
});

it('filters products by category', function () {
    $category2 = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_active' => true,
    ]);

    Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $this->category->id,
        'name' => 'Product A',
        'is_active' => true,
    ]);

    Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category2->id,
        'name' => 'Product B',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->get(route('pos.products', ['category_id' => $this->category->id]))
        ->assertSuccessful();

    expect($response->json('products'))->toHaveCount(1);
    expect($response->json('products.0.name'))->toBe('Product A');
});

it('searches products by name and barcode', function () {
    Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Apple iPhone',
        'barcode' => '123456789',
        'is_active' => true,
    ]);

    Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Samsung Galaxy',
        'barcode' => '987654321',
        'is_active' => true,
    ]);

    // Search by name
    $response = $this->actingAs($this->user)
        ->get(route('pos.products', ['search' => 'iPhone']))
        ->assertSuccessful();

    expect($response->json('products'))->toHaveCount(1);

    // Search by barcode
    $response = $this->actingAs($this->user)
        ->get(route('pos.products', ['search' => '987654321']))
        ->assertSuccessful();

    expect($response->json('products'))->toHaveCount(1);
    expect($response->json('products.0.name'))->toBe('Samsung Galaxy');
});
