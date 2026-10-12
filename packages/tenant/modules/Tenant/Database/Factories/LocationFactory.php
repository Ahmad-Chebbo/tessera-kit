<?php

namespace App\Modules\Tenant\Database\Factories;

use App\Modules\Tenant\Enums\LocationType;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'branch_id' => Branch::factory(),
            'name' => fake()->word().' Location',
            'type' => LocationType::Branch,
            'is_active' => true,
        ];
    }

    public function warehouse(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LocationType::Warehouse,
            'branch_id' => null,
        ]);
    }

    public function branch(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => LocationType::Branch,
        ]);
    }
}
