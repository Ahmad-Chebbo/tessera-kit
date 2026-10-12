<?php

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

function apiCrew(Company $company, Branch $branch, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 09:10:00', 'UTC'));

    $this->company = Company::factory()->create(['timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->manager = apiCrew($this->company, $this->branch, ['view attendance', 'record attendance']);
    $this->employee = apiCrew($this->company, $this->branch, ['record attendance']);
    $this->outsider = apiCrew($this->company, $this->branch, []);

    $morning = ShiftTemplate::factory()->create(['company_id' => $this->company->id, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $morning->id, 'date' => '2026-10-14']);
});

describe('checking in and out', function () {
    it('needs a token and the record permission', function () {
        $this->postJson('/api/v1/attendance/check-in')->assertUnauthorized();

        Sanctum::actingAs($this->outsider);
        $this->postJson('/api/v1/attendance/check-in')->assertForbidden();
    });

    it('checks in against the assigned shift and out again', function () {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/attendance/current')->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/v1/attendance/check-in')
            ->assertCreated()
            ->assertJsonPath('data.late_minutes', 10)
            ->assertJsonPath('data.source', 'self')
            ->assertJsonPath('data.check_out_at', null);

        $this->getJson('/api/v1/attendance/current')->assertOk()->assertJsonPath('data.late_minutes', 10);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 17:00:00', 'UTC'));

        $this->postJson('/api/v1/attendance/check-out')->assertOk()->assertJsonPath('data.worked_minutes', 470);
        $this->getJson('/api/v1/attendance/current')->assertJsonPath('data', null);
    });

    it('refuses a second check in and a check out without one', function () {
        Sanctum::actingAs($this->employee);

        $this->postJson('/api/v1/attendance/check-out')->assertUnprocessable()->assertJsonStructure(['message']);

        $this->postJson('/api/v1/attendance/check-in')->assertCreated();
        $this->postJson('/api/v1/attendance/check-in')->assertUnprocessable()->assertJsonPath('message', 'You are already checked in.');
    });
});

describe('listing', function () {
    beforeEach(function () {
        foreach ([$this->employee, $this->manager] as $person) {
            AttendanceRecord::create(['company_id' => $this->company->id, 'user_id' => $person->id, 'work_date' => '2026-10-14', 'check_in_at' => '2026-10-14 08:00:00', 'check_out_at' => '2026-10-14 16:00:00', 'worked_minutes' => 480, 'source' => 'self']);
        }
    });

    it('shows everyone to people who can view attendance and narrows by employee', function () {
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/attendance')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/attendance?user_id='.$this->employee->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.id', $this->employee->id)->assertJsonPath('data.0.worked_minutes', 480);
    });

    it('shows employees only their own records', function () {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/attendance?user_id='.$this->manager->id)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.user.id', $this->employee->id);
    });
});

describe('the report', function () {
    it('needs the view permission', function () {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/attendance/report')->assertForbidden();
    });

    it('summarises hours and lateness per employee', function () {
        AttendanceRecord::create(['company_id' => $this->company->id, 'user_id' => $this->employee->id, 'work_date' => '2026-10-13', 'check_in_at' => '2026-10-13 09:30:00', 'check_out_at' => '2026-10-13 17:00:00', 'late_minutes' => 30, 'worked_minutes' => 450, 'source' => 'self']);
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/attendance/report?date_from=2026-10-13&date_to=2026-10-14')
            ->assertOk()
            ->assertJsonPath('period.from', '2026-10-13')
            ->assertJsonPath('data.totals.records', 1)
            ->assertJsonPath('data.employees.0.late_count', 1)
            ->assertJsonPath('data.employees.0.worked_minutes', 450);
    });
});
