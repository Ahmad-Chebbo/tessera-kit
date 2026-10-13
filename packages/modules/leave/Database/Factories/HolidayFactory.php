<?php

namespace App\Modules\Leave\Database\Factories;

use App\Modules\Leave\Models\Holiday;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'date' => fake()->unique()->dateTimeBetween('+1 month', '+1 year')->format('Y-m-d'),
            'name' => fake()->words(2, true),
        ];
    }
}
