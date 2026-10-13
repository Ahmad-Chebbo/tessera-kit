<?php

namespace App\Modules\Leave\Database\Factories;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'leave_type_id' => LeaveType::factory(),
            'starts_on' => '2026-11-02',
            'ends_on' => '2026-11-03',
            'days' => 2,
            'reason' => null,
            'status' => LeaveStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(['status' => LeaveStatus::Approved, 'decided_at' => now()]);
    }
}
