<?php

namespace App\Modules\Attendance\Database\Factories;

use App\Modules\Attendance\Models\AttendanceExcuse;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceExcuse>
 */
class AttendanceExcuseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => fn (array $attributes) => User::find($attributes['user_id'])->company_id,
            'date' => now()->subDay()->toDateString(),
            'reason' => 'Sick leave',
        ];
    }
}
