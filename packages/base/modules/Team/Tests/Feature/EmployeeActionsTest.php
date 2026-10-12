<?php

use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Actions\CreateEmployeeAction;
use App\Modules\Team\Actions\RemoveEmployeeAction;
use App\Modules\Team\Actions\UpdateEmployeeAction;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function actionActor(Tenant $tenant, Branch $branch, bool $seesEveryBranch): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);

    if ($seesEveryBranch) {
        $user->givePermissionTo(Permission::findOrCreate('view all branches'));
    }

    return $user;
}

function actionEmployeeData(array $overrides = []): array
{
    return [
        'name' => 'Layla Haddad',
        'email' => 'layla-'.uniqid().'@example.com',
        'password' => 'Str0ng!Passw0rd#2026',
        'role' => 'Cashier',
        'is_active' => true,
        'job_title' => 'Senior cashier',
        ...$overrides,
    ];
}

beforeEach(function () {
    Role::findOrCreate('Cashier');
    Role::findOrCreate('Manager');

    $this->tenant = Tenant::factory()->create();
    $this->north = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->south = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
});

describe('creating an employee', function () {
    it('adds the employee with a role, a profile and an activity entry', function () {
        $actor = actionActor($this->tenant, $this->north, true);

        $employee = app(CreateEmployeeAction::class)->execute($actor, actionEmployeeData(['branch_id' => $this->south->id]));

        expect($employee->tenant_id)->toBe($this->tenant->id)
            ->and($employee->branch_id)->toBe($this->south->id)
            ->and($employee->hasRole('Cashier'))->toBeTrue()
            ->and($employee->employeeProfile->job_title)->toBe('Senior cashier')
            ->and(Hash::check('Str0ng!Passw0rd#2026', $employee->password))->toBeTrue()
            ->and(ActivityLog::where('event', 'employee.created')->where('user_id', $actor->id)->count())->toBe(1);
    });

    it('books people who cannot see every branch to their own branch', function () {
        $actor = actionActor($this->tenant, $this->north, false);

        $employee = app(CreateEmployeeAction::class)->execute($actor, actionEmployeeData(['branch_id' => $this->south->id]));

        expect($employee->branch_id)->toBe($this->north->id);
    });

    it('refuses when the plan has no room left', function () {
        $actor = actionActor($this->tenant, $this->north, true);
        $this->tenant->subscriptions()->delete();
        app(SubscriptionService::class)->start($this->tenant, Plan::factory()->create(['max_users' => 1]));

        app(CreateEmployeeAction::class)->execute($actor, actionEmployeeData(['branch_id' => $this->north->id]));
    })->throws(InvalidArgumentException::class, 'Your plan has reached its employee limit.');
});

describe('updating an employee', function () {
    it('changes the details, the role and the password', function () {
        $actor = actionActor($this->tenant, $this->north, true);
        $employee = app(CreateEmployeeAction::class)->execute($actor, actionEmployeeData(['branch_id' => $this->north->id]));

        app(UpdateEmployeeAction::class)->execute($actor, $employee, actionEmployeeData([
            'name' => 'Layla H.', 'email' => $employee->email, 'role' => 'Manager', 'branch_id' => $this->south->id, 'password' => 'An0ther!Passw0rd#2026',
        ]));

        $employee->refresh();

        expect($employee->name)->toBe('Layla H.')
            ->and($employee->branch_id)->toBe($this->south->id)
            ->and($employee->hasRole('Manager'))->toBeTrue()->and($employee->hasRole('Cashier'))->toBeFalse()
            ->and(Hash::check('An0ther!Passw0rd#2026', $employee->password))->toBeTrue()
            ->and(ActivityLog::where('event', 'employee.updated')->count())->toBe(1);
    });

    it('keeps the old password when none is sent and the branch when the actor is held to theirs', function () {
        $boss = actionActor($this->tenant, $this->north, true);
        $employee = app(CreateEmployeeAction::class)->execute($boss, actionEmployeeData(['branch_id' => $this->south->id]));
        $oldHash = $employee->password;

        app(UpdateEmployeeAction::class)->execute(actionActor($this->tenant, $this->north, false), $employee, actionEmployeeData(['email' => $employee->email, 'branch_id' => $this->north->id, 'password' => null]));

        expect($employee->fresh()->password)->toBe($oldHash)->and($employee->fresh()->branch_id)->toBe($this->south->id);
    });
});

describe('removing an employee', function () {
    it('deletes the employee and logs it', function () {
        $actor = actionActor($this->tenant, $this->north, true);
        $employee = app(CreateEmployeeAction::class)->execute($actor, actionEmployeeData(['branch_id' => $this->north->id]));

        app(RemoveEmployeeAction::class)->execute($actor, $employee);

        expect(User::find($employee->id))->toBeNull()
            ->and(ActivityLog::where('event', 'employee.deleted')->where('user_id', $actor->id)->count())->toBe(1);
    });

    it('never removes the actor themselves', function () {
        $actor = actionActor($this->tenant, $this->north, true);

        app(RemoveEmployeeAction::class)->execute($actor, $actor);
    })->throws(InvalidArgumentException::class, 'You cannot delete your own account.');
});
