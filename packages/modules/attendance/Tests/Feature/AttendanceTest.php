<?php

use App\Modules\Attendance\Models\AttendanceExcuse;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function crewMember(Company $company, Branch $branch, array $permissions): User
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
    $this->manager = crewMember($this->company, $this->branch, ['view attendance', 'manage attendance', 'record attendance', 'view activity log']);
    $this->employee = crewMember($this->company, $this->branch, ['record attendance']);

    $this->other = Company::factory()->create();
    $this->otherBranch = Branch::factory()->create(['company_id' => $this->other->id]);
    $this->otherManager = crewMember($this->other, $this->otherBranch, ['view attendance', 'manage attendance', 'record attendance']);

    $this->morning = ShiftTemplate::factory()->create(['company_id' => $this->company->id, 'starts_at' => '09:00', 'ends_at' => '17:00']);
});

describe('self check-in and check-out', function () {
    it('checks in against the assigned shift and records lateness', function () {
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-14']);

        $this->actingAs($this->employee)->post(route('attendance.check-in'))->assertSessionHas('success');

        $record = AttendanceRecord::firstOrFail();
        expect($record->work_date)->toBe('2026-10-14')
            ->and($record->shift_template_id)->toBe($this->morning->id)
            ->and($record->late_minutes)->toBe(10)
            ->and($record->source)->toBe('self')
            ->and($record->isOpen())->toBeTrue()
            ->and(ActivityLog::where('event', 'attendance.check_in')->count())->toBe(1);
    });

    it('checks in without a shift and counts no lateness', function () {
        $this->actingAs($this->employee)->post(route('attendance.check-in'));

        $record = AttendanceRecord::firstOrFail();
        expect($record->shift_template_id)->toBeNull()->and($record->late_minutes)->toBe(0);
    });

    it('rejects a second check-in and a check-out without one', function () {
        $this->actingAs($this->employee)->post(route('attendance.check-out'))->assertSessionHas('error');
        $this->actingAs($this->employee)->post(route('attendance.check-in'));
        $this->actingAs($this->employee)->post(route('attendance.check-in'))->assertSessionHas('error');

        expect(AttendanceRecord::count())->toBe(1);
    });

    it('tracks worked hours and early leave on check-out', function () {
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-14']);
        $this->actingAs($this->employee)->post(route('attendance.check-in'));

        $this->travelTo(CarbonImmutable::parse('2026-10-14 16:00:00', 'UTC'));
        $this->actingAs($this->employee)->post(route('attendance.check-out'))->assertSessionHas('success');

        $record = AttendanceRecord::firstOrFail();
        expect($record->worked_minutes)->toBe(410)
            ->and($record->early_leave_minutes)->toBe(60)
            ->and($record->isOpen())->toBeFalse()
            ->and(ActivityLog::where('event', 'attendance.check_out')->count())->toBe(1);

        $this->actingAs($this->employee)->post(route('attendance.check-in'))->assertSessionHas('success');
    });

    it('measures against the company timezone', function () {
        $this->company->update(['timezone' => 'Asia/Beirut']);
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-14']);

        $this->actingAs($this->employee)->post(route('attendance.check-in'));

        expect(AttendanceRecord::firstOrFail()->late_minutes)->toBe(190);
    });

    it('requires the record permission', function () {
        $viewer = crewMember($this->company, $this->branch, ['view attendance']);

        $this->actingAs($viewer)->post(route('attendance.check-in'))->assertForbidden();
    });

    it('shares the open record with every page', function () {
        $this->actingAs($this->employee)->post(route('attendance.check-in'));

        $this->actingAs($this->employee)->get(route('attendance.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Attendance/Index')->where('attendance.id', AttendanceRecord::first()->id));
    });
});

describe('manager corrections', function () {
    it('adds a record for an employee and derives the hours', function () {
        $this->actingAs($this->manager)->post(route('attendance.store'), [
            'user_id' => $this->employee->id,
            'check_in_at' => '2026-10-13T09:00',
            'check_out_at' => '2026-10-13T17:30',
            'notes' => 'Forgot to check out',
        ])->assertSessionHas('success');

        $record = AttendanceRecord::firstOrFail();
        expect($record->worked_minutes)->toBe(510)
            ->and($record->source)->toBe('manager')
            ->and($record->created_by)->toBe($this->manager->id)
            ->and(ActivityLog::where('event', 'attendance.adjusted')->count())->toBe(1);
    });

    it('corrects an existing record', function () {
        $record = AttendanceRecord::factory()->create(['user_id' => $this->employee->id]);

        $this->actingAs($this->manager)->put(route('attendance.update', $record), [
            'user_id' => $this->employee->id,
            'check_in_at' => '2026-10-13T08:00',
            'check_out_at' => '2026-10-13T12:00',
        ])->assertSessionHas('success');

        expect($record->fresh()->worked_minutes)->toBe(240);
    });

    it('validates times and employees', function () {
        $this->actingAs($this->manager)->post(route('attendance.store'), [
            'user_id' => $this->employee->id,
            'check_in_at' => '2026-10-13T17:00',
            'check_out_at' => '2026-10-13T09:00',
        ])->assertSessionHasErrors('check_out_at');

        $this->actingAs($this->manager)->post(route('attendance.store'), [
            'user_id' => $this->employee->id,
            'check_in_at' => '2026-12-01T09:00',
        ])->assertSessionHasErrors('check_in_at');

        $this->actingAs($this->manager)->post(route('attendance.store'), [
            'user_id' => $this->otherManager->id,
            'check_in_at' => '2026-10-13T09:00',
        ])->assertSessionHasErrors('user_id');
    });

    it('keeps other companies out', function () {
        $record = AttendanceRecord::factory()->create(['user_id' => $this->employee->id]);

        $this->actingAs($this->otherManager)->delete(route('attendance.destroy', $record))->assertForbidden();
        $this->actingAs($this->otherManager)->put(route('attendance.update', $record), [
            'user_id' => $this->otherManager->id, 'check_in_at' => '2026-10-13T09:00',
        ])->assertForbidden();
        $this->actingAs($this->manager)->delete(route('attendance.destroy', $record))->assertRedirect();
        expect(AttendanceRecord::count())->toBe(0);
    });

    it('does not let employees edit records', function () {
        $this->actingAs($this->employee)->post(route('attendance.store'), [
            'user_id' => $this->employee->id, 'check_in_at' => '2026-10-13T09:00',
        ])->assertForbidden();
    });
});

describe('history', function () {
    it('shows employees only their own records', function () {
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id]);
        AttendanceRecord::factory()->create(['user_id' => $this->manager->id]);
        AttendanceRecord::factory()->create(['user_id' => $this->otherManager->id]);

        $this->actingAs($this->employee)->get(route('attendance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1)->where('can.viewAll', false));
        $this->actingAs($this->manager)->get(route('attendance.index'))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 2)->where('can.viewAll', true));
    });

    it('filters by employee and date range and ignores a bad date', function () {
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-10-13']);
        AttendanceRecord::factory()->create(['user_id' => $this->manager->id, 'work_date' => '2026-10-13']);
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-09-01']);

        $this->actingAs($this->manager)->get(route('attendance.index', ['user_id' => $this->employee->id, 'date_from' => '2026-10-01']))
            ->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->actingAs($this->manager)->get(route('attendance.index', ['date_from' => 'nonsense']))->assertOk();
    });
});

describe('absences', function () {
    it('lists past assigned shifts without a record and ignores today and the future', function () {
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-12']);
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-13']);
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-14']);
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-20']);
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-10-13']);

        $this->actingAs($this->manager)->get(route('attendance.absences'))
            ->assertInertia(fn (Assert $page) => $page->component('Attendance/Absences')->has('absences', 1)->where('absences.0.date', '2026-10-12'));
    });

    it('flags excused absences and lets a manager excuse and unexcuse', function () {
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-12']);

        $this->actingAs($this->manager)->post(route('attendance.excuses.store'), [
            'user_id' => $this->employee->id, 'date' => '2026-10-12', 'reason' => 'Sick leave',
        ])->assertSessionHas('success');

        $this->actingAs($this->manager)->get(route('attendance.absences'))
            ->assertInertia(fn (Assert $page) => $page->where('absences.0.excused', true)->where('absences.0.reason', 'Sick leave'));

        $this->actingAs($this->manager)->delete(route('attendance.excuses.destroy', AttendanceExcuse::first()))->assertRedirect();
        expect(AttendanceExcuse::count())->toBe(0);
    });

    it('validates excuses', function () {
        $this->actingAs($this->manager)->post(route('attendance.excuses.store'), [
            'user_id' => $this->employee->id, 'date' => '2026-10-14', 'reason' => '',
        ])->assertSessionHasErrors(['date', 'reason']);
    });

    it('is limited to viewers and the own company', function () {
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-12']);

        $this->actingAs($this->employee)->get(route('attendance.absences'))->assertForbidden();
        $this->actingAs($this->otherManager)->get(route('attendance.absences'))
            ->assertInertia(fn (Assert $page) => $page->has('absences', 0));
    });
});

describe('report', function () {
    it('totals hours, lateness and absences per employee', function () {
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-10-13', 'worked_minutes' => 480, 'late_minutes' => 15]);
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-10-12', 'worked_minutes' => 420]);
        AttendanceRecord::factory()->create(['user_id' => $this->manager->id, 'work_date' => '2026-10-13', 'worked_minutes' => 300]);
        ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-11']);

        $this->actingAs($this->manager)->get(route('attendance.report', ['date_from' => '2026-10-10', 'date_to' => '2026-10-14']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Attendance/Report')
                ->where('report.totals.worked_minutes', 1200)
                ->where('report.totals.late_count', 1)
                ->where('report.totals.absences', 1)
                ->has('report.employees', 2)
                ->has('report.daily', 2)
                ->where('report.daily.1.headcount', 2));
    });

    it('is scoped to the company and needs the view permission', function () {
        AttendanceRecord::factory()->create(['user_id' => $this->employee->id, 'work_date' => '2026-10-13']);

        $this->actingAs($this->employee)->get(route('attendance.report'))->assertForbidden();
        $this->actingAs($this->otherManager)->get(route('attendance.report'))
            ->assertInertia(fn (Assert $page) => $page->where('report.totals.records', 0));
    });
});

it('gives the team permissions to the right roles when seeded', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $cashier = Role::findByName('Cashier');
    $manager = Role::findByName('Manager');

    expect($cashier->hasPermissionTo('record attendance'))->toBeTrue()
        ->and($cashier->hasPermissionTo('manage attendance'))->toBeFalse()
        ->and($manager->hasPermissionTo('manage shifts'))->toBeTrue()
        ->and($manager->hasPermissionTo('view activity log'))->toBeTrue();
});
