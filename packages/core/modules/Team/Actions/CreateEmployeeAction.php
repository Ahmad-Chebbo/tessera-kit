<?php

namespace App\Modules\Team\Actions;

use App\Modules\Company\Services\BranchAccess;
use App\Modules\Subscription\Services\Entitlements;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use App\Modules\Team\Services\EmployeeProfileService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class CreateEmployeeAction
{
    public function __construct(
        private readonly Entitlements $entitlements,
        private readonly BranchAccess $access,
        private readonly EmployeeProfileService $profiles,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Adds an employee to the actor's company. People who cannot see every branch can only add to their own.
     *
     * @param  array<string, mixed>  $data  name, email, password, role, branch_id, is_active and the profile fields.
     *
     * @throws InvalidArgumentException when the plan has no room for another employee.
     */
    public function execute(User $actor, array $data): User
    {
        if (! $this->entitlements->hasRoomFor($actor->company_id, 'users')) {
            throw new InvalidArgumentException('Your plan has reached its employee limit.');
        }

        $employee = DB::transaction(function () use ($actor, $data): User {
            $employee = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'company_id' => $actor->company_id,
                'branch_id' => $this->access->canSeeAll($actor) ? $data['branch_id'] : $actor->branch_id,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $employee->assignRole(Role::resolveFor($actor->company_id, $data['role']));
            $this->profiles->save($employee, $data);

            return $employee;
        });

        $this->activity->record(ActivityEvent::EmployeeCreated, "Added employee {$employee->name}", $employee, ['role' => $data['role']], $actor);

        return $employee;
    }
}
