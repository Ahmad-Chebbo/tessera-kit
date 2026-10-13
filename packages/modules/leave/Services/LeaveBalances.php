<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Team\Models\User;

/**
 * What an employee may still take. A request counts against the year it starts in, and a pending one holds its days
 * until it is decided. A type without a yearly allowance is unlimited.
 */
class LeaveBalances
{
    /**
     * @return list<array{type_id: int, name: string, is_paid: bool, entitlement: float|null, used: float, pending: float, remaining: float|null}>
     */
    public function for(User $user, int $year): array
    {
        return LeaveType::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (LeaveType $type): array => [
                'type_id' => $type->id,
                'name' => $type->name,
                'is_paid' => $type->is_paid,
                'entitlement' => $type->days_per_year,
                'used' => $this->days($user->id, $type->id, $year, [LeaveStatus::Approved]),
                'pending' => $this->days($user->id, $type->id, $year, [LeaveStatus::Pending]),
                'remaining' => $this->remaining($user->id, $type, $year),
            ])
            ->all();
    }

    /**
     * @return float|null Days left, or null when the type has no limit.
     */
    public function remaining(int $userId, LeaveType $type, int $year, ?int $ignoreRequestId = null): ?float
    {
        if ($type->days_per_year === null) {
            return null;
        }

        return $type->days_per_year - $this->days($userId, $type->id, $year, LeaveStatus::holding(), $ignoreRequestId);
    }

    /**
     * @param  list<LeaveStatus>  $statuses
     */
    private function days(int $userId, int $typeId, int $year, array $statuses, ?int $ignoreRequestId = null): float
    {
        return (float) LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('leave_type_id', $typeId)
            ->whereIn('status', array_map(fn (LeaveStatus $status): string => $status->value, $statuses))
            ->whereBetween('starts_on', ["{$year}-01-01", "{$year}-12-31"])
            ->when($ignoreRequestId, fn ($query) => $query->whereKeyNot($ignoreRequestId))
            ->sum('days');
    }
}
