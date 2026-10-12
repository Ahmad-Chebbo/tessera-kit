<?php

namespace App\Modules\Company\Database\Factories;

use App\Modules\Company\Enums\LocationType;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
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
            'company_id' => Company::factory(),
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
