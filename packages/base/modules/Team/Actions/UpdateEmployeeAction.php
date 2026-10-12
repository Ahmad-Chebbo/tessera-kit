<?php

namespace App\Modules\Team\Actions;

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use App\Modules\Team\Services\EmployeeProfileService;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tessera\Core\Contracts\BranchScope;

class UpdateEmployeeAction
{
    public function __construct(
        private readonly BranchScope $access,
        private readonly EmployeeProfileService $profiles,
        private readonly ActivityLogger $activity,
        private readonly TeamFeatures $features,
    ) {}

    /**
     * Updates an employee. Only people who can see every branch may move someone to another branch.
     *
     * @param  array<string, mixed>  $data  name, email, role, branch_id, is_active, optional password and the profile fields.
     */
    public function execute(User $actor, User $employee, array $data): User
    {
        DB::transaction(function () use ($actor, $employee, $data): void {
            $employee->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'branch_id' => $this->access->canSeeAll($actor) && array_key_exists('branch_id', $data) ? $data['branch_id'] : $employee->branch_id,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (! empty($data['password'])) {
                $employee->update(['password' => Hash::make($data['password'])]);
            }

            $employee->syncRoles([Role::resolveFor($employee->company_id, $data['role'])]);

            if ($this->features->employeeProfiles()) {
                $this->profiles->save($employee, $data);
            }
        });

        $this->activity->record(ActivityEvent::EmployeeUpdated, "Updated employee {$employee->name}", $employee, ['role' => $data['role']], $actor);

        return $employee;
    }
}
