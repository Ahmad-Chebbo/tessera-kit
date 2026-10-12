<?php

namespace App\Modules\Inventory\Database\Factories;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'product_id' => Product::factory(),
            'location_id' => Location::factory(),
            'type' => StockMovementType::Purchase,
            'quantity' => fake()->numberBetween(1, 100),
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function purchase(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StockMovementType::Purchase,
            'quantity' => abs($attributes['quantity'] ?? fake()->numberBetween(1, 100)),
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StockMovementType::Sale,
            'quantity' => -abs($attributes['quantity'] ?? fake()->numberBetween(1, 10)),
        ]);
    }

    public function adjustment(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => StockMovementType::Adjustment,
        ]);
    }
}
