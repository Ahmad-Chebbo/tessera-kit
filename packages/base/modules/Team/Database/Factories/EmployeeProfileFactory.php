<?php

namespace App\Modules\Team\Database\Factories;

use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeProfile>
 */
class EmployeeProfileFactory extends Factory
{
    protected $model = EmployeeProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attributes) => User::find($attributes['user_id'])->tenant_id,
            'employee_number' => 'EMP-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'phone' => fake()->phoneNumber(),
            'job_title' => fake()->jobTitle(),
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 month'),
        ];
    }
}
