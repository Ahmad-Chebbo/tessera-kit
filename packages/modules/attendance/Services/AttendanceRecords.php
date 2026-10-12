<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Services\BranchAccess;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The attendance records a person may look at: everyone's (narrowed to a branch or an employee) for people who can
 * view attendance, only their own for everyone else.
 */
class AttendanceRecords
{
    public function __construct(private readonly BranchAccess $access) {}

    /**
     * @return Builder<AttendanceRecord>
     */
    public function visibleTo(User $actor, CarbonInterface $from, CarbonInterface $to, ?int $userId = null, mixed $branchId = null): Builder
    {
        $seesEveryone = $actor->can('view attendance');

        return AttendanceRecord::query()
            ->with(['user:id,name', 'shiftTemplate:id,name'])
            ->where('tenant_id', $actor->tenant_id)
            ->when(! $seesEveryone, fn (Builder $query) => $query->where('user_id', $actor->id))
            ->when($seesEveryone, fn (Builder $query) => $this->access->scopeThroughUser($query, $actor, $branchId))
            ->when($seesEveryone && $userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('check_in_at');
    }
}
