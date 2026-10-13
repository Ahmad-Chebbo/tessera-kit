<?php

namespace App\Modules\Leave\Database\Factories;

use App\Modules\Leave\Models\LeaveType;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word().' leave',
            'days_per_year' => 14,
            'is_paid' => true,
            'is_active' => true,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(['is_paid' => false, 'days_per_year' => null]);
    }
}
