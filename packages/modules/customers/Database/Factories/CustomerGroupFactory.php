<?php

namespace App\Modules\Customers\Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Customers\Models\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerGroup>
 */
class CustomerGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->word(),
            'description' => null,
        ];
    }
}
