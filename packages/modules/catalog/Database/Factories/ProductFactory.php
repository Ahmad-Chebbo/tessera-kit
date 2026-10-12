<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $basePrice = fake()->randomFloat(2, 5, 500);
        $costPrice = $basePrice * 0.6;

        return [
            'tenant_id' => Tenant::factory(),
            'category_id' => Category::factory(),
            'name' => fake()->words(3, true),
            'barcode' => fake()->ean13(),
            'sku' => strtoupper(fake()->bothify('SKU-????-####')),
            'base_price' => $basePrice,
            'sale_price' => null,
            'cost_price' => $costPrice,
            'currency' => 'USD',
            'unit' => 'piece',
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function onSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_price' => $attributes['base_price'] * 0.8,
        ]);
    }
}
