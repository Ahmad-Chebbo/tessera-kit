<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Team\Models\User;
use Tessera\Core\Contracts\ReportingLines;

/**
 * Who may decide a request. Someone with the approve permission may decide the requests of other people in the tenant.
 * When the Organization module says who reports to whom, that is narrowed to the employee's manager, and anyone with the
 * manage permission may still decide any request, so an employee without a manager is not stuck.
 */
class LeaveApprovals
{
    public function __construct(private readonly ReportingLines $lines) {}

    public function canDecide(User $actor, LeaveRequest $request): bool
    {
        if ($actor->tenant_id !== $request->tenant_id || $actor->id === $request->user_id || ! $actor->can('approve leave')) {
            return false;
        }

        return $actor->can('manage leave') || ! $this->lines->available() || $this->lines->managerOf($request->user_id) === $actor->id;
    }
}
