<?php

namespace App\Modules\Organization\Database\Factories;

use App\Modules\Organization\Models\Position;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->jobTitle(),
            'is_active' => true,
        ];
    }
}
