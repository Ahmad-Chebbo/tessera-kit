<?php

use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Models\Holiday;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\ApprovedLeaveCalendar;
use App\Modules\Organization\Models\EmployeeAssignment;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Contracts\Defaults\NoLeaveCalendar;
use Tessera\Core\Contracts\Defaults\NoReportingLines;
use Tessera\Core\Contracts\LeaveCalendar;
use Tessera\Core\Contracts\ReportingLines;

function leaveStaff(Tenant $tenant, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->travelTo('2026-10-26 09:00:00');

    $this->tenant = Tenant::factory()->create();
    $this->employee = leaveStaff($this->tenant, ['request leave']);
    $this->approver = leaveStaff($this->tenant, ['request leave', 'approve leave']);
    $this->admin = leaveStaff($this->tenant, ['request leave', 'approve leave', 'manage leave']);
    $this->annual = LeaveType::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Annual', 'days_per_year' => 10]);

    $this->other = Tenant::factory()->create();
    $this->otherAdmin = leaveStaff($this->other, ['request leave', 'approve leave', 'manage leave']);
});

function requestLeave(mixed $test, User $user, array $data = [])
{
    return $test->actingAs($user)->post(route('leave.requests.store'), [
        'leave_type_id' => $test->annual->id,
        'starts_on' => '2026-11-02',
        'ends_on' => '2026-11-06',
        ...$data,
    ]);
}

describe('the page', function () {
    it('shows the balances and the requests of the person only', function () {
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'days' => 3]);
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->approver->id, 'leave_type_id' => $this->annual->id]);

        $this->actingAs($this->employee)
            ->get(route('leave.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Leave/Index')
                ->has('requests', 1)
                ->where('balances.0.name', 'Annual')
                ->where('balances.0.pending', 3)
                ->where('balances.0.remaining', 7)
                ->where('can.approve', false)
                ->where('can.manage', false)
                ->has('approvals', 0));
    });

    it('is closed to people who cannot request leave', function () {
        $this->actingAs(leaveStaff($this->tenant, []))->get(route('leave.index'))->assertForbidden();
    });

    it('lists who is away in the next thirty days to everyone', function () {
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->approver->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-10-28', 'ends_on' => '2026-10-29']);
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->approver->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-02']);

        $this->actingAs($this->employee)->get(route('leave.index'))->assertInertia(fn (Assert $page) => $page->has('away', 1));
    });
});

describe('requesting leave', function () {
    it('counts working days only and files a pending request', function () {
        Holiday::factory()->create(['tenant_id' => $this->tenant->id, 'date' => '2026-11-04']);

        requestLeave($this, $this->employee, ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-09', 'reason' => 'Trip'])->assertSessionHasNoErrors();

        $leave = LeaveRequest::firstOrFail();
        expect($leave->days)->toBe(5.0)
            ->and($leave->status)->toBe(LeaveStatus::Pending)
            ->and($leave->user_id)->toBe($this->employee->id)
            ->and($leave->reason)->toBe('Trip');
    });

    it('follows the configured weekend', function () {
        config(['leave.weekend' => ['friday', 'saturday']]);

        requestLeave($this, $this->employee, ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-08']);

        expect(LeaveRequest::firstOrFail()->days)->toBe(5.0);
    });

    it('refuses dates with no working day', function () {
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-07', 'ends_on' => '2026-11-08'])->assertSessionHasErrors('ends_on');
    });

    it('refuses dates in the past, backwards dates and another tenant type', function () {
        requestLeave($this, $this->employee, ['starts_on' => '2026-10-20', 'ends_on' => '2026-10-21'])->assertSessionHasErrors('starts_on');
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-05', 'ends_on' => '2026-11-03'])->assertSessionHasErrors('ends_on');

        $foreign = LeaveType::factory()->create(['tenant_id' => $this->other->id]);
        requestLeave($this, $this->employee, ['leave_type_id' => $foreign->id])->assertSessionHasErrors('leave_type_id');

        expect(LeaveRequest::count())->toBe(0);
    });

    it('lets someone who manages leave file dates that have passed', function () {
        requestLeave($this, $this->admin, ['starts_on' => '2026-10-19', 'ends_on' => '2026-10-20'])->assertSessionHasNoErrors();

        expect(LeaveRequest::count())->toBe(1);
    });

    it('refuses overlapping requests but not cancelled ones', function () {
        requestLeave($this, $this->employee);
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-06', 'ends_on' => '2026-11-10'])->assertSessionHasErrors('starts_on');

        LeaveRequest::query()->update(['status' => LeaveStatus::Cancelled]);
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-06', 'ends_on' => '2026-11-10'])->assertSessionHasNoErrors();
    });

    it('refuses more days than the allowance has left, counting pending ones', function () {
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-02', 'ends_on' => '2026-11-10']);
        requestLeave($this, $this->employee, ['starts_on' => '2026-11-16', 'ends_on' => '2026-11-20'])->assertSessionHasErrors('leave_type_id');

        expect(LeaveRequest::count())->toBe(1);
    });

    it('does not limit a type without a yearly allowance', function () {
        $unpaid = LeaveType::factory()->unpaid()->create(['tenant_id' => $this->tenant->id]);

        requestLeave($this, $this->employee, ['leave_type_id' => $unpaid->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-12-31'])->assertSessionHasNoErrors();

        expect(LeaveRequest::firstOrFail()->days)->toBe(44.0);
    });

    it('does not let a switched off type be requested', function () {
        $this->annual->update(['is_active' => false]);

        requestLeave($this, $this->employee)->assertSessionHasErrors('leave_type_id');
    });
});

describe('deciding', function () {
    beforeEach(function () {
        $this->leave = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'days' => 5]);
        EmployeeAssignment::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'manager_id' => $this->approver->id]);
    });

    it('approves a request and records who decided', function () {
        $this->actingAs($this->approver)->post(route('leave.requests.approve', $this->leave))->assertRedirect();

        $this->leave->refresh();
        expect($this->leave->status)->toBe(LeaveStatus::Approved)
            ->and($this->leave->decided_by)->toBe($this->approver->id)
            ->and($this->leave->decided_at)->not->toBeNull();
    });

    it('rejects a request with a note', function () {
        $this->actingAs($this->approver)->post(route('leave.requests.reject', $this->leave), ['note' => 'Busy week'])->assertRedirect();

        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Rejected)
            ->and($this->leave->fresh()->decision_note)->toBe('Busy week');
    });

    it('shows the approver the pending requests of others only', function () {
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->approver->id, 'leave_type_id' => $this->annual->id]);
        LeaveRequest::factory()->create(['tenant_id' => $this->other->id, 'user_id' => $this->otherAdmin->id]);

        $this->actingAs($this->approver)->get(route('leave.index'))->assertInertia(fn (Assert $page) => $page->has('approvals', 1)->where('approvals.0.employee', $this->employee->name));
    });

    it('does not let people decide their own request', function () {
        $own = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id, 'leave_type_id' => $this->annual->id]);

        $this->actingAs($this->admin)->post(route('leave.requests.approve', $own))->assertForbidden();
    });

    it('does not let people without the permission or from another tenant decide', function () {
        $this->actingAs($this->employee)->post(route('leave.requests.approve', $this->leave))->assertForbidden();
        $this->actingAs($this->otherAdmin)->post(route('leave.requests.approve', $this->leave))->assertForbidden();

        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Pending);
    });

    it('sends the request to the manager when the organization says who that is', function () {
        $colleague = leaveStaff($this->tenant, ['request leave', 'approve leave']);

        $this->actingAs($colleague)->post(route('leave.requests.approve', $this->leave))->assertForbidden();
        $this->actingAs($this->admin)->post(route('leave.requests.approve', $this->leave))->assertRedirect();

        $this->leave->update(['status' => LeaveStatus::Pending]);
        $this->actingAs($this->approver)->post(route('leave.requests.approve', $this->leave))->assertRedirect();
        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Approved);
    });

    it('lets anyone with the permission decide when nothing says who the manager is', function () {
        app()->bind(ReportingLines::class, NoReportingLines::class);
        $colleague = leaveStaff($this->tenant, ['request leave', 'approve leave']);

        $this->actingAs($colleague)->post(route('leave.requests.approve', $this->leave))->assertRedirect();

        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Approved);
    });

    it('decides a request only once', function () {
        $this->actingAs($this->approver)->post(route('leave.requests.approve', $this->leave));

        $this->actingAs($this->approver)->post(route('leave.requests.reject', $this->leave))->assertSessionHasErrors('request');
        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Approved);
    });

    it('checks the allowance again when approving', function () {
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'days' => 8, 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-10']);

        $this->actingAs($this->approver)->post(route('leave.requests.approve', $this->leave))->assertSessionHasErrors('request');
        expect($this->leave->fresh()->status)->toBe(LeaveStatus::Pending);
    });
});

describe('cancelling', function () {
    it('lets people cancel their own pending request', function () {
        $leave = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id]);

        $this->actingAs($this->employee)->post(route('leave.requests.cancel', $leave))->assertRedirect();

        expect($leave->fresh()->status)->toBe(LeaveStatus::Cancelled);
    });

    it('lets people cancel approved leave that has not started, but not leave that has', function () {
        $future = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-03']);
        $started = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-28']);

        $this->actingAs($this->employee)->post(route('leave.requests.cancel', $future))->assertSessionHasNoErrors();
        $this->actingAs($this->employee)->post(route('leave.requests.cancel', $started))->assertSessionHasErrors('request');

        expect($future->fresh()->status)->toBe(LeaveStatus::Cancelled)
            ->and($started->fresh()->status)->toBe(LeaveStatus::Approved);
    });

    it('lets someone who manages leave cancel leave that is under way', function () {
        $started = LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-10-26', 'ends_on' => '2026-10-28']);

        $this->actingAs($this->admin)->post(route('leave.requests.cancel', $started))->assertSessionHasNoErrors();

        expect($started->fresh()->status)->toBe(LeaveStatus::Cancelled);
    });

    it('does not let someone else or another tenant cancel', function () {
        $leave = LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id]);

        $this->actingAs($this->approver)->post(route('leave.requests.cancel', $leave))->assertForbidden();
        $this->actingAs($this->otherAdmin)->post(route('leave.requests.cancel', $leave))->assertForbidden();
    });
});

describe('leave types and holidays', function () {
    it('creates, updates and deletes a leave type', function () {
        $this->actingAs($this->admin)->post(route('leave.types.store'), ['name' => 'Sick', 'days_per_year' => 7, 'is_paid' => true, 'is_active' => true])->assertSessionHasNoErrors();

        $type = LeaveType::where('name', 'Sick')->firstOrFail();
        expect($type->tenant_id)->toBe($this->tenant->id)->and($type->days_per_year)->toBe(7.0);

        $this->actingAs($this->admin)->put(route('leave.types.update', $type), ['name' => 'Sick leave', 'days_per_year' => null])->assertSessionHasNoErrors();
        expect($type->fresh()->name)->toBe('Sick leave')->and($type->fresh()->days_per_year)->toBeNull();

        $this->actingAs($this->admin)->delete(route('leave.types.destroy', $type))->assertRedirect();
        expect(LeaveType::where('name', 'Sick leave')->exists())->toBeFalse();
    });

    it('keeps a leave type that has requests', function () {
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id]);

        $this->actingAs($this->admin)->delete(route('leave.types.destroy', $this->annual))->assertSessionHasErrors('type');
        expect($this->annual->fresh())->not->toBeNull();
    });

    it('keeps type names unique within a tenant only', function () {
        $this->actingAs($this->admin)->post(route('leave.types.store'), ['name' => 'Annual'])->assertSessionHasErrors('name');
        $this->actingAs($this->otherAdmin)->post(route('leave.types.store'), ['name' => 'Annual'])->assertSessionHasNoErrors();
    });

    it('adds and removes holidays, one per date', function () {
        $this->actingAs($this->admin)->post(route('leave.holidays.store'), ['date' => '2026-12-25', 'name' => 'Christmas'])->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('leave.holidays.store'), ['date' => '2026-12-25', 'name' => 'Again'])->assertSessionHasErrors('date');

        $holiday = Holiday::firstOrFail();
        $this->actingAs($this->admin)->delete(route('leave.holidays.destroy', $holiday))->assertRedirect();
        expect(Holiday::count())->toBe(0);
    });

    it('keeps managing for those who hold the permission, inside their tenant', function () {
        $this->actingAs($this->approver)->post(route('leave.types.store'), ['name' => 'Sick'])->assertForbidden();
        $this->actingAs($this->otherAdmin)->put(route('leave.types.update', $this->annual), ['name' => 'Taken'])->assertForbidden();
        $this->actingAs($this->otherAdmin)->delete(route('leave.types.destroy', $this->annual))->assertForbidden();
    });
});

describe('the leave calendar', function () {
    it('lists the working days of approved leave only, with the type and whether it is paid', function () {
        $unpaid = LeaveType::factory()->unpaid()->create(['tenant_id' => $this->tenant->id, 'name' => 'Unpaid']);
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $unpaid->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-10']);
        LeaveRequest::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->approver->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-11-05', 'ends_on' => '2026-11-06']);

        $days = app(ApprovedLeaveCalendar::class)->daysOff($this->tenant->id, '2026-11-01', '2026-11-30');

        expect(array_keys($days))->toBe(["{$this->employee->id}|2026-11-05", "{$this->employee->id}|2026-11-06", "{$this->employee->id}|2026-11-09", "{$this->employee->id}|2026-11-10"])
            ->and($days["{$this->employee->id}|2026-11-05"])->toBe(['label' => 'Unpaid', 'paid' => false]);
    });

    it('can be limited to some people and a range', function () {
        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-11-02', 'ends_on' => '2026-11-06']);

        $calendar = app(LeaveCalendar::class);

        expect($calendar)->toBeInstanceOf(ApprovedLeaveCalendar::class)
            ->and($calendar->daysOff($this->tenant->id, '2026-11-04', '2026-11-04', [$this->employee->id]))->toHaveCount(1)
            ->and($calendar->daysOff($this->tenant->id, '2026-11-04', '2026-11-04', [$this->approver->id]))->toBe([])
            ->and($calendar->daysOff($this->other->id, '2026-11-01', '2026-11-30'))->toBe([]);
    });

    it('shows nobody away without the module', function () {
        expect((new NoLeaveCalendar)->available())->toBeFalse()
            ->and((new NoLeaveCalendar)->daysOff(1, '2026-11-01', '2026-11-30'))->toBe([]);
    });

    it('stops attendance from counting approved leave as an absence', function () {
        $this->travelTo('2026-11-10 09:00:00');
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        foreach (['2026-11-03', '2026-11-04'] as $date) {
            ShiftAssignment::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'shift_template_id' => $template->id, 'date' => $date]);
        }

        LeaveRequest::factory()->approved()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'leave_type_id' => $this->annual->id, 'starts_on' => '2026-11-03', 'ends_on' => '2026-11-03']);

        $absences = app(AttendanceService::class)->absences($this->tenant->id, CarbonImmutable::parse('2026-11-02'), CarbonImmutable::parse('2026-11-09'))->keyBy('date');

        expect($absences['2026-11-03']['excused'])->toBeTrue()
            ->and($absences['2026-11-03']['on_leave'])->toBeTrue()
            ->and($absences['2026-11-03']['reason'])->toBe('Annual')
            ->and($absences['2026-11-04']['excused'])->toBeFalse()
            ->and($absences['2026-11-04']['on_leave'])->toBeFalse();
    });
});
