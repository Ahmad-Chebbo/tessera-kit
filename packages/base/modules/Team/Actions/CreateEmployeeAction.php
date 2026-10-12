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
use InvalidArgumentException;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Core\Contracts\Tenancy;

class CreateEmployeeAction
{
    public function __construct(
        private readonly PlanLimits $limits,
        private readonly BranchScope $access,
        private readonly EmployeeProfileService $profiles,
        private readonly ActivityLogger $activity,
        private readonly TeamFeatures $features,
        private readonly Tenancy $tenancy,
    ) {}

    /**
     * Adds an employee to the actor's tenant. People who cannot see every branch can only add to their own.
     *
     * @param  array<string, mixed>  $data  name, email, password, role, branch_id, is_active and the profile fields.
     *
     * @throws InvalidArgumentException when the plan has no room for another employee.
     */
    public function execute(User $actor, array $data): User
    {
        if (! $this->limits->hasRoomFor($actor->tenant_id, 'users')) {
            throw new InvalidArgumentException('Your plan has reached its employee limit.');
        }

        $employee = DB::transaction(function () use ($actor, $data): User {
            $employee = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                ...$this->tenancy->attributes($actor->tenant_id),
                ...($this->access->available() ? ['branch_id' => $this->access->canSeeAll($actor) ? ($data['branch_id'] ?? null) : $actor->branch_id] : []),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $employee->assignRole(Role::resolveFor($this->tenancy->roleOwner($actor), $data['role']));

            if ($this->features->employeeProfiles()) {
                $this->profiles->save($employee, $data);
            }

            return $employee;
        });

        $this->activity->record(ActivityEvent::EmployeeCreated, "Added employee {$employee->name}", $employee, ['role' => $data['role']], $actor);

        return $employee;
    }
}
