<?php

use App\Modules\Attendance\Models\AttendanceExcuse;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 12:00:00', 'UTC'));

    $this->tenant = Tenant::factory()->create(['timezone' => 'Asia/Beirut']);
    $this->north = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->south = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->boss = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->north->id])->givePermissionTo(Permission::findOrCreate('view all branches'));
    $this->northManager = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->north->id]);
    $this->southStaff = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->south->id]);
});

describe('recording attendance for an employee', function () {
    it('stores the moment the times stand for, not the clock on the wall', function () {
        $record = app(AttendanceService::class)->saveFor($this->boss, $this->southStaff->id, '2026-10-13T09:00', '2026-10-13T17:30', null);

        $stored = DB::table('attendance_records')->where('id', $record->id)->first();

        expect($stored->check_in_at)->toBe('2026-10-13 06:00:00')
            ->and($stored->check_out_at)->toBe('2026-10-13 14:30:00')
            ->and($record->fresh()->work_date)->toBe('2026-10-13');
    });

    it('is on time when it starts with the shift in the tenant timezone', function () {
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'starts_at' => '09:00', 'ends_at' => '17:00']);
        ShiftAssignment::factory()->create(['user_id' => $this->southStaff->id, 'shift_template_id' => $template->id, 'date' => '2026-10-13']);

        $onTime = app(AttendanceService::class)->saveFor($this->boss, $this->southStaff->id, '2026-10-13T09:00', '2026-10-13T17:00', null);

        expect($onTime->late_minutes)->toBe(0)->and($onTime->early_leave_minutes)->toBe(0)->and($onTime->shiftTemplate->id)->toBe($template->id);
    });

    it('records the worked time between the two moments', function () {
        $record = app(AttendanceService::class)->saveFor($this->boss, $this->southStaff->id, '2026-10-13T09:00', '2026-10-13T17:30', 'Covered a shift');

        expect($record->user_id)->toBe($this->southStaff->id)
            ->and($record->worked_minutes)->toBe(510)
            ->and($record->notes)->toBe('Covered a shift');
    });

    it('corrects an existing record', function () {
        $service = app(AttendanceService::class);
        $record = $service->saveFor($this->boss, $this->southStaff->id, '2026-10-13T09:00', null, null);

        $service->saveFor($this->boss, $this->southStaff->id, '2026-10-13T09:00', '2026-10-13T12:00', null, $record);

        expect($record->fresh()->worked_minutes)->toBe(180);
    });

    it('does not reach employees of another branch for people held to theirs', function () {
        app(AttendanceService::class)->saveFor($this->northManager, $this->southStaff->id, '2026-10-13T09:00', null, null);
    })->throws(HttpException::class);

    it('does not reach employees of another tenant', function () {
        $stranger = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        app(AttendanceService::class)->saveFor($this->boss, $stranger->id, '2026-10-13T09:00', null, null);
    })->throws(ModelNotFoundException::class);
});

describe('excusing an absence', function () {
    it('excuses a day once and changes the reason afterwards', function () {
        $service = app(AttendanceService::class);

        $service->excuse($this->boss, $this->southStaff->id, '2026-10-12', 'Sick');
        $service->excuse($this->boss, $this->southStaff->id, '2026-10-12', 'Family emergency');

        expect(AttendanceExcuse::count())->toBe(1)
            ->and(AttendanceExcuse::first()->reason)->toBe('Family emergency')
            ->and(AttendanceExcuse::first()->created_by)->toBe($this->boss->id);
    });

    it('stays inside the actor\'s branch', function () {
        app(AttendanceService::class)->excuse($this->northManager, $this->southStaff->id, '2026-10-12', 'Sick');
    })->throws(HttpException::class);
});
