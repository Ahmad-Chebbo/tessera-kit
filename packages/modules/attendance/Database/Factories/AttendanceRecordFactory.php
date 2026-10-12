<?php

namespace App\Modules\Attendance\Database\Factories;

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $checkIn = now()->subDays(1)->setTime(9, 0);

        return [
            'user_id' => User::factory(),
            'company_id' => fn (array $attributes) => User::find($attributes['user_id'])->company_id,
            'work_date' => $checkIn->toDateString(),
            'check_in_at' => $checkIn,
            'check_out_at' => $checkIn->copy()->addHours(8),
            'worked_minutes' => 480,
            'source' => AttendanceRecord::SELF,
        ];
    }
}
