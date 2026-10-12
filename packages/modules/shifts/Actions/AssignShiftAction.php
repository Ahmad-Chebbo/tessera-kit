<?php

namespace App\Modules\Shifts\Actions;

use App\Modules\Company\Services\BranchAccess;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;

class AssignShiftAction
{
    public function __construct(
        private readonly BranchAccess $access,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Puts an employee on a shift for a day. Assigning the same shift twice changes nothing.
     *
     * @param  array{user_id: int, shift_template_id: int, date: string}  $data
     */
    public function execute(User $actor, array $data): ShiftAssignment
    {
        $this->access->ensure($actor, User::query()->whereKey($data['user_id'])->value('branch_id'));

        $assignment = ShiftAssignment::firstOrCreate(
            ['user_id' => $data['user_id'], 'shift_template_id' => $data['shift_template_id'], 'date' => $data['date']],
            ['company_id' => $actor->company_id],
        );

        if ($assignment->wasRecentlyCreated) {
            $employee = User::find($data['user_id']);
            $this->activity->record(ActivityEvent::ShiftAssigned, "Assigned {$employee->name} to a shift on {$data['date']}", $assignment, [], $actor);
        }

        return $assignment;
    }
}
