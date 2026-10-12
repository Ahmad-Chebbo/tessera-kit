<?php

namespace App\Modules\Sales\Database\Seeders;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * A demo store with a branch, a warehouse, categories, products and stock, for trying the till.
 */
class DemoStoreSeeder extends Seeder
{
    public function run(): void
    {
        // Create Company
        $company = Company::create([
            'name' => 'Tessera Demo Store',
            'currency' => 'USD',
            'locale' => 'en',
            'tax_rate' => 10,
            'timezone' => 'UTC',
            'receipt_header' => 'Welcome to Tessera Demo Store',
            'receipt_footer' => 'Thank you for shopping with us!',
            'address' => '123 Main Street, Demo City',
            'phone' => '+1 234 567 8900',
            'email' => 'info@tessera.test',
            'is_active' => true,
        ]);

        app(SubscriptionService::class)->start($company, Plan::where('slug', 'pro')->firstOr(fn () => Plan::where('slug', 'legacy')->firstOrFail()));

        // Create Branch
        $branch = Branch::create([
            'company_id' => $company->id,
            'name' => 'Main Branch',
            'address' => '123 Main Street, Demo City',
            'phone' => '+1 234 567 8900',
            'is_active' => true,
        ]);

        // Create Location for the branch
        $location = Location::create([
            'company_id' => $company->id,
            'branch_id' => $branch->id,
            'name' => 'Main Branch Stock',
            'type' => 'branch',
            'is_active' => true,
        ]);

        // Create Warehouse
        $warehouse = Location::create([
            'company_id' => $company->id,
            'branch_id' => null,
            'name' => 'Central Warehouse',
            'type' => 'warehouse',
            'is_active' => true,
        ]);

        // Update existing user or create test user
        $user = User::first();
        if ($user) {
            $user->update([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
            ]);
        } else {
            $user = User::create([
                'name' => 'Test Cashier',
                'email' => 'cashier@tessera.test',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);
        }

        // Assign Admin role to the user
        if (! $user->hasRole('Owner')) {
            $user->assignRole('Owner');
        }

        // Create Categories
        $categories = [
            'Electronics' => [
                ['name' => 'iPhone 15 Pro', 'price' => 999.99, 'cost' => 750.00, 'stock' => 25],
                ['name' => 'Samsung Galaxy S24', 'price' => 899.99, 'cost' => 680.00, 'stock' => 30],
                ['name' => 'AirPods Pro', 'price' => 249.99, 'cost' => 180.00, 'stock' => 50],
                ['name' => 'MacBook Air M3', 'price' => 1299.99, 'cost' => 950.00, 'stock' => 15],
                ['name' => 'iPad Pro 12.9"', 'price' => 1099.99, 'cost' => 820.00, 'stock' => 20],
            ],
            'Accessories' => [
                ['name' => 'Phone Case - Black', 'price' => 29.99, 'cost' => 8.00, 'stock' => 100],
                ['name' => 'Phone Case - Clear', 'price' => 24.99, 'cost' => 6.00, 'stock' => 100],
                ['name' => 'Screen Protector', 'price' => 14.99, 'cost' => 3.00, 'stock' => 200],
                ['name' => 'USB-C Cable 2m', 'price' => 19.99, 'cost' => 5.00, 'stock' => 150],
                ['name' => 'Wireless Charger', 'price' => 39.99, 'cost' => 15.00, 'stock' => 75],
            ],
            'Audio' => [
                ['name' => 'Sony WH-1000XM5', 'price' => 349.99, 'cost' => 250.00, 'stock' => 20],
                ['name' => 'JBL Flip 6', 'price' => 129.99, 'cost' => 80.00, 'stock' => 35],
                ['name' => 'Bose QuietComfort', 'price' => 279.99, 'cost' => 200.00, 'stock' => 18],
                ['name' => 'Marshall Stanmore II', 'price' => 349.99, 'cost' => 220.00, 'stock' => 10],
            ],
            'Wearables' => [
                ['name' => 'Apple Watch Series 9', 'price' => 399.99, 'cost' => 300.00, 'stock' => 25],
                ['name' => 'Samsung Galaxy Watch 6', 'price' => 329.99, 'cost' => 240.00, 'stock' => 20],
                ['name' => 'Fitbit Charge 6', 'price' => 159.99, 'cost' => 100.00, 'stock' => 40],
            ],
            'Gaming' => [
                ['name' => 'PlayStation 5', 'price' => 499.99, 'cost' => 400.00, 'stock' => 8],
                ['name' => 'Xbox Series X', 'price' => 499.99, 'cost' => 400.00, 'stock' => 10],
                ['name' => 'Nintendo Switch OLED', 'price' => 349.99, 'cost' => 280.00, 'stock' => 15],
                ['name' => 'PS5 Controller', 'price' => 69.99, 'cost' => 45.00, 'stock' => 50],
                ['name' => 'Xbox Controller', 'price' => 59.99, 'cost' => 40.00, 'stock' => 45],
            ],
            'Beverages' => [
                ['name' => 'Coca-Cola 500ml', 'price' => 2.49, 'cost' => 1.00, 'stock' => 200],
                ['name' => 'Pepsi 500ml', 'price' => 2.49, 'cost' => 1.00, 'stock' => 180],
                ['name' => 'Red Bull 250ml', 'price' => 3.99, 'cost' => 2.00, 'stock' => 150],
                ['name' => 'Water Bottle 1L', 'price' => 1.99, 'cost' => 0.50, 'stock' => 300],
                ['name' => 'Orange Juice 1L', 'price' => 4.99, 'cost' => 2.50, 'stock' => 80],
            ],
            'Snacks' => [
                ['name' => 'Doritos Nacho', 'price' => 4.99, 'cost' => 2.00, 'stock' => 100],
                ['name' => 'Pringles Original', 'price' => 3.99, 'cost' => 1.80, 'stock' => 120],
                ['name' => 'KitKat', 'price' => 1.99, 'cost' => 0.80, 'stock' => 200],
                ['name' => 'Snickers', 'price' => 1.99, 'cost' => 0.85, 'stock' => 180],
                ['name' => 'M&Ms Peanut', 'price' => 2.49, 'cost' => 1.00, 'stock' => 150],
            ],
        ];

        $barcodeCounter = 1000000000001;

        foreach ($categories as $categoryName => $products) {
            $category = Category::create([
                'company_id' => $company->id,
                'name' => $categoryName,
                'description' => "Products in the {$categoryName} category",
                'is_active' => true,
            ]);

            foreach ($products as $productData) {
                $product = Product::create([
                    'company_id' => $company->id,
                    'category_id' => $category->id,
                    'name' => $productData['name'],
                    'barcode' => (string) $barcodeCounter++,
                    'sku' => 'SKU-'.strtoupper(substr(md5($productData['name']), 0, 8)),
                    'base_price' => $productData['price'],
                    'sale_price' => null,
                    'cost_price' => $productData['cost'],
                    'currency' => 'USD',
                    'unit' => 'piece',
                    'description' => $productData['name'].' - High quality product',
                    'is_active' => true,
                ]);

                // Add initial stock
                StockMovement::create([
                    'company_id' => $company->id,
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => $productData['stock'],
                    'notes' => 'Initial stock',
                    'created_by' => $user->id,
                ]);
            }
        }

        // Add some products with low stock for testing alerts
        $lowStockCategory = Category::where('name', 'Electronics')->first();

        $lowStockProducts = [
            ['name' => 'Limited Edition Headphones', 'price' => 199.99, 'cost' => 120.00, 'stock' => 3],
            ['name' => 'Rare Gaming Mouse', 'price' => 89.99, 'cost' => 50.00, 'stock' => 5],
            ['name' => 'Special USB Hub', 'price' => 49.99, 'cost' => 25.00, 'stock' => 2],
        ];

        foreach ($lowStockProducts as $productData) {
            $product = Product::create([
                'company_id' => $company->id,
                'category_id' => $lowStockCategory->id,
                'name' => $productData['name'],
                'barcode' => (string) $barcodeCounter++,
                'sku' => 'SKU-'.strtoupper(substr(md5($productData['name']), 0, 8)),
                'base_price' => $productData['price'],
                'cost_price' => $productData['cost'],
                'currency' => 'USD',
                'unit' => 'piece',
                'is_active' => true,
            ]);

            StockMovement::create([
                'company_id' => $company->id,
                'product_id' => $product->id,
                'location_id' => $location->id,
                'type' => StockMovementType::Purchase,
                'quantity' => $productData['stock'],
                'notes' => 'Initial stock - low quantity',
                'created_by' => $user->id,
            ]);
        }

        $this->command->info('POS Test Data seeded successfully!');
        $this->command->info('Company: '.$company->name);
        $this->command->info('Branch: '.$branch->name);
        $this->command->info('User: '.$user->email);
        $this->command->info('Categories: '.Category::count());
        $this->command->info('Products: '.Product::count());
    }
}
