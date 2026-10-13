<?php

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveBalances;
use App\Modules\Leave\Services\LeaveDays;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Files a leave request for the employee: the days are counted without weekends and holidays, it may not overlap
 * another request, and it may not take more than what is left of the type's yearly allowance.
 */
class RequestLeaveAction
{
    public function __construct(private readonly LeaveDays $days, private readonly LeaveBalances $balances) {}

    /**
     * @param  array{leave_type_id: int, starts_on: string, ends_on: string, reason?: string|null}  $data
     *
     * @throws ValidationException
     */
    public function execute(User $employee, array $data): LeaveRequest
    {
        $type = LeaveType::query()->where('tenant_id', $employee->tenant_id)->where('is_active', true)->findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['starts_on']);
        $end = CarbonImmutable::parse($data['ends_on']);
        $count = count($this->days->between($employee->tenant_id, $start, $end));

        if ($count === 0) {
            throw ValidationException::withMessages(['ends_on' => 'These dates have no working days: they are weekend or holidays.']);
        }

        $overlaps = LeaveRequest::query()
            ->where('user_id', $employee->id)
            ->whereIn('status', array_map(fn (LeaveStatus $status): string => $status->value, LeaveStatus::holding()))
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['starts_on' => 'You already have leave on some of these dates.']);
        }

        $remaining = $this->balances->remaining($employee->id, $type, $start->year);

        if ($remaining !== null && $count > $remaining) {
            throw ValidationException::withMessages(['leave_type_id' => "Only {$remaining} days of {$type->name} are left this year."]);
        }

        return LeaveRequest::create([
            'tenant_id' => $employee->tenant_id,
            'user_id' => $employee->id,
            'leave_type_id' => $type->id,
            'starts_on' => $start->toDateString(),
            'ends_on' => $end->toDateString(),
            'days' => $count,
            'reason' => $data['reason'] ?? null,
            'status' => LeaveStatus::Pending,
        ]);
    }
}
