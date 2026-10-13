<?php

namespace App\Modules\Leave\Actions;

use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Services\LeaveApprovals;
use App\Modules\Leave\Services\LeaveBalances;
use App\Modules\Team\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Approves or rejects a pending request. Approving checks the allowance again, because other requests may have been
 * approved since this one was filed.
 */
class DecideLeaveAction
{
    public function __construct(private readonly LeaveApprovals $approvals, private readonly LeaveBalances $balances) {}

    /**
     * @throws AuthorizationException when the actor may not decide this request.
     * @throws ValidationException when the request is not pending or the allowance is used up.
     */
    public function execute(User $actor, LeaveRequest $request, bool $approve, ?string $note = null): LeaveRequest
    {
        if (! $this->approvals->canDecide($actor, $request)) {
            throw new AuthorizationException('You cannot decide this request.');
        }

        if ($request->status !== LeaveStatus::Pending) {
            throw ValidationException::withMessages(['request' => 'This request has already been decided or cancelled.']);
        }

        if ($approve) {
            $remaining = $this->balances->remaining($request->user_id, $request->type, $request->starts_on->year, $request->id);

            if ($remaining !== null && $request->days > $remaining) {
                throw ValidationException::withMessages(['request' => "Only {$remaining} days of {$request->type->name} are left this year."]);
            }
        }

        $request->update([
            'status' => $approve ? LeaveStatus::Approved : LeaveStatus::Rejected,
            'decided_by' => $actor->id,
            'decided_at' => now(),
            'decision_note' => $note,
        ]);

        return $request;
    }
}
