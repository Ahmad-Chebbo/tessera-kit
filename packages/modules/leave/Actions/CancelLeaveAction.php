<?php

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Team\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Withdraws a request. Employees may cancel their own pending requests and approved ones that have not started;
 * people who manage leave may cancel any that has not ended.
 */
class CancelLeaveAction
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function execute(User $actor, LeaveRequest $request): LeaveRequest
    {
        $manager = $actor->tenant_id === $request->tenant_id && $actor->can('manage leave');

        if (! $manager && $actor->id !== $request->user_id) {
            throw new AuthorizationException('You cannot cancel this request.');
        }

        $today = now()->toDateString();
        $cancellable = match ($request->status) {
            LeaveStatus::Pending => true,
            LeaveStatus::Approved => $manager ? $request->ends_on->toDateString() >= $today : $request->starts_on->toDateString() > $today,
            default => false,
        };

        if (! $cancellable) {
            throw ValidationException::withMessages(['request' => 'This request can no longer be cancelled.']);
        }

        $request->update(['status' => LeaveStatus::Cancelled]);

        return $request;
    }
}
