<?php

namespace App\Modules\Team\Actions;

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use InvalidArgumentException;

class RemoveEmployeeAction
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /**
     * @throws InvalidArgumentException when the actor tries to remove their own account.
     */
    public function execute(User $actor, User $employee): void
    {
        if ($employee->id === $actor->id) {
            throw new InvalidArgumentException('You cannot delete your own account.');
        }

        $name = $employee->name;
        $employee->delete();

        $this->activity->record(ActivityEvent::EmployeeDeleted, "Removed employee {$name}", null, ['name' => $name], $actor);
    }
}
