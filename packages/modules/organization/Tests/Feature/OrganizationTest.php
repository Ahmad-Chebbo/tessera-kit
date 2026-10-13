<?php

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\EmployeeAssignment;
use App\Modules\Organization\Models\Position;
use App\Modules\Organization\Services\AssignedReportingLines;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Contracts\Defaults\NoReportingLines;
use Tessera\Core\Contracts\ReportingLines;

function organizationStaff(Tenant $tenant, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->admin = organizationStaff($this->tenant, ['view organization', 'manage organization']);
    $this->viewer = organizationStaff($this->tenant, ['view organization']);

    $this->other = Tenant::factory()->create();
    $this->otherAdmin = organizationStaff($this->other, ['view organization', 'manage organization']);
});

describe('the page', function () {
    it('lists departments, positions and employees of the tenant only', function () {
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sales']);
        Department::factory()->create(['tenant_id' => $this->other->id, 'name' => 'Secret']);
        Position::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Clerk']);

        $this->actingAs($this->admin)
            ->get(route('organization.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organization/Index')
                ->has('departments', 1)
                ->where('departments.0.name', 'Sales')
                ->has('positions', 1)
                ->has('employees', 2)
                ->where('can.manage', true));
    });

    it('is closed to people without the view permission', function () {
        $this->actingAs(organizationStaff($this->tenant, []))->get(route('organization.index'))->assertForbidden();
    });

    it('shows a viewer the page without the manage controls', function () {
        $this->actingAs($this->viewer)->get(route('organization.index'))->assertInertia(fn (Assert $page) => $page->where('can.manage', false));
        $this->actingAs($this->viewer)->post(route('organization.departments.store'), ['name' => 'Sales'])->assertForbidden();
    });
});

describe('departments', function () {
    it('creates, updates and deletes a department', function () {
        $this->actingAs($this->admin)->post(route('organization.departments.store'), ['name' => 'Sales', 'head_id' => $this->viewer->id])->assertRedirect();

        $department = Department::where('tenant_id', $this->tenant->id)->firstOrFail();
        expect($department->head_id)->toBe($this->viewer->id);

        $this->actingAs($this->admin)->put(route('organization.departments.update', $department), ['name' => 'Revenue'])->assertRedirect();
        expect($department->fresh()->name)->toBe('Revenue');

        $this->actingAs($this->admin)->delete(route('organization.departments.destroy', $department))->assertRedirect();
        expect(Department::count())->toBe(0);
    });

    it('keeps names unique within a tenant only', function () {
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Sales']);

        $this->actingAs($this->admin)->post(route('organization.departments.store'), ['name' => 'Sales'])->assertSessionHasErrors('name');
        $this->actingAs($this->otherAdmin)->post(route('organization.departments.store'), ['name' => 'Sales'])->assertSessionHasNoErrors();
    });

    it('refuses a parent from another tenant or one that loops', function () {
        $sales = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $team = Department::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $sales->id]);
        $foreign = Department::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->admin)->put(route('organization.departments.update', $sales), ['name' => $sales->name, 'parent_id' => $team->id])->assertSessionHasErrors('parent_id');
        $this->actingAs($this->admin)->put(route('organization.departments.update', $sales), ['name' => $sales->name, 'parent_id' => $sales->id])->assertSessionHasErrors('parent_id');
        $this->actingAs($this->admin)->put(route('organization.departments.update', $team), ['name' => $team->name, 'parent_id' => $foreign->id])->assertSessionHasErrors('parent_id');
    });

    it('does not delete a department that still has departments under it', function () {
        $sales = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'parent_id' => $sales->id]);

        $this->actingAs($this->admin)->delete(route('organization.departments.destroy', $sales))->assertSessionHasErrors('department');
        expect($sales->fresh())->not->toBeNull();
    });

    it('keeps the employees of a deleted department', function () {
        $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment = EmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->viewer->id, 'department_id' => $department->id]);

        $this->actingAs($this->admin)->delete(route('organization.departments.destroy', $department));

        expect($assignment->fresh()->department_id)->toBeNull();
    });

    it('does not let another tenant touch a department', function () {
        $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->otherAdmin)->put(route('organization.departments.update', $department), ['name' => 'Taken'])->assertForbidden();
        $this->actingAs($this->otherAdmin)->delete(route('organization.departments.destroy', $department))->assertForbidden();
    });
});

describe('positions', function () {
    it('creates, updates and deletes a position', function () {
        $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)->post(route('organization.positions.store'), ['name' => 'Clerk', 'department_id' => $department->id])->assertRedirect();
        $position = Position::where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->admin)->put(route('organization.positions.update', $position), ['name' => 'Senior clerk'])->assertRedirect();
        expect($position->fresh()->name)->toBe('Senior clerk');

        $this->actingAs($this->admin)->delete(route('organization.positions.destroy', $position))->assertRedirect();
        expect(Position::count())->toBe(0);
    });

    it('refuses a department of another tenant', function () {
        $foreign = Department::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->admin)->post(route('organization.positions.store'), ['name' => 'Clerk', 'department_id' => $foreign->id])->assertSessionHasErrors('department_id');
    });
});

describe('employees', function () {
    it('assigns a department, a position and a manager, then changes them', function () {
        $department = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $position = Position::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->admin)
            ->put(route('organization.employees.assign', $this->viewer), ['department_id' => $department->id, 'position_id' => $position->id, 'manager_id' => $this->admin->id])
            ->assertRedirect();

        $this->actingAs($this->admin)->put(route('organization.employees.assign', $this->viewer), ['manager_id' => null])->assertRedirect();

        $assignment = EmployeeAssignment::where('user_id', $this->viewer->id)->firstOrFail();
        expect($assignment->manager_id)->toBeNull()
            ->and($assignment->department_id)->toBe($department->id)
            ->and(EmployeeAssignment::count())->toBe(1);
    });

    it('refuses a manager from another tenant, oneself, and a loop', function () {
        $this->actingAs($this->admin)->put(route('organization.employees.assign', $this->viewer), ['manager_id' => $this->otherAdmin->id])->assertSessionHasErrors('manager_id');
        $this->actingAs($this->admin)->put(route('organization.employees.assign', $this->viewer), ['manager_id' => $this->viewer->id])->assertSessionHasErrors('manager_id');

        EmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->viewer->id, 'manager_id' => $this->admin->id]);

        $this->actingAs($this->admin)->put(route('organization.employees.assign', $this->admin), ['manager_id' => $this->viewer->id])->assertSessionHasErrors('manager_id');
    });

    it('does not assign an employee of another tenant', function () {
        $this->actingAs($this->admin)->put(route('organization.employees.assign', $this->otherAdmin), [])->assertForbidden();
    });
});

describe('reporting lines', function () {
    it('answers who a person reports to and who reports to a manager', function () {
        EmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->viewer->id, 'manager_id' => $this->admin->id]);

        $lines = app(ReportingLines::class);

        expect($lines)->toBeInstanceOf(AssignedReportingLines::class)
            ->and($lines->available())->toBeTrue()
            ->and($lines->managerOf($this->viewer->id))->toBe($this->admin->id)
            ->and($lines->managerOf($this->admin->id))->toBeNull()
            ->and($lines->reportsOf($this->admin->id))->toBe([$this->viewer->id]);
    });

    it('has nobody report to anyone without the module', function () {
        $none = new NoReportingLines;

        expect($none->available())->toBeFalse()
            ->and($none->managerOf(1))->toBeNull()
            ->and($none->reportsOf(1))->toBe([]);
    });
});
