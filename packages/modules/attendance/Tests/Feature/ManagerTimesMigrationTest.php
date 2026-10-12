<?php

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;

function managerTimesMigration(): object
{
    return require dirname(__DIR__, 2).'/Database/Migrations/2026_10_13_100100_store_manager_attendance_times_as_instants.php';
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['timezone' => 'Asia/Beirut']);
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->employee = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);

    $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'starts_at' => '09:00', 'ends_at' => '17:00']);
    ShiftAssignment::factory()->create(['user_id' => $this->employee->id, 'shift_template_id' => $template->id, 'date' => '2026-10-13']);

    $row = fn (string $source, string $updatedAt) => DB::table('attendance_records')->insertGetId([
        'tenant_id' => $this->tenant->id, 'user_id' => $this->employee->id, 'source' => $source, 'work_date' => '2026-10-13',
        'check_in_at' => '2026-10-13 09:00:00', 'check_out_at' => '2026-10-13 17:00:00', 'late_minutes' => 180, 'early_leave_minutes' => 0, 'worked_minutes' => 480,
        'created_at' => $updatedAt, 'updated_at' => $updatedAt,
    ]);

    $this->old = $row(AttendanceRecord::MANAGER, '2026-10-01 10:00:00');
    $this->self = $row(AttendanceRecord::SELF, '2026-10-01 10:00:00');
    $this->recent = $row(AttendanceRecord::MANAGER, '2026-10-12 08:00:00');
});

it('turns old manager entries into the moments they stand for and measures them again', function () {
    managerTimesMigration()->up();

    $old = DB::table('attendance_records')->find($this->old);

    expect($old->check_in_at)->toBe('2026-10-13 06:00:00')
        ->and($old->check_out_at)->toBe('2026-10-13 14:00:00')
        ->and($old->late_minutes)->toBe(0)
        ->and($old->early_leave_minutes)->toBe(0)
        ->and($old->updated_at)->toBe('2026-10-01 10:00:00');
});

it('leaves self check-ins and records written after the fix alone', function () {
    managerTimesMigration()->up();

    expect(DB::table('attendance_records')->find($this->self)->check_in_at)->toBe('2026-10-13 09:00:00')
        ->and(DB::table('attendance_records')->find($this->recent)->check_in_at)->toBe('2026-10-13 09:00:00');
});

it('can be rolled back', function () {
    managerTimesMigration()->up();
    managerTimesMigration()->down();

    expect(DB::table('attendance_records')->find($this->old)->check_in_at)->toBe('2026-10-13 09:00:00');
});
