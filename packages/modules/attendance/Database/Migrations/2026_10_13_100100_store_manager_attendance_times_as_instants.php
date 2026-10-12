<?php

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Records a manager typed in were saved as the company's wall clock time instead of the moment it stands for, so a
 * 09:00 entry in Beirut was read back as 09:00 UTC. This rewrites those records as real moments and measures
 * lateness and hours again. Only records written before the fix are touched.
 */
return new class extends Migration
{
    private const WRITTEN_BEFORE = '2026-10-12 00:30:00';

    public function up(): void
    {
        $this->convert(fn (string $stored, string $zone): CarbonImmutable => CarbonImmutable::parse($stored, $zone)->setTimezone(config('app.timezone')));
    }

    public function down(): void
    {
        $this->convert(fn (string $stored, string $zone): CarbonImmutable => CarbonImmutable::parse($stored, config('app.timezone'))->setTimezone($zone));
    }

    /**
     * @param  Closure(string, string): CarbonImmutable  $shift
     */
    private function convert(Closure $shift): void
    {
        $rows = DB::table('attendance_records')
            ->join('users', 'users.id', '=', 'attendance_records.user_id')
            ->join('companies', 'companies.id', '=', 'attendance_records.company_id')
            ->where('attendance_records.source', AttendanceRecord::MANAGER)
            ->where('attendance_records.updated_at', '<=', self::WRITTEN_BEFORE)
            ->select('attendance_records.id', 'attendance_records.user_id', 'attendance_records.check_in_at', 'attendance_records.check_out_at', 'companies.timezone')
            ->get();

        foreach ($rows as $row) {
            $zone = $row->timezone ?: config('app.timezone');

            DB::table('attendance_records')->where('id', $row->id)->update([
                'check_in_at' => $shift($row->check_in_at, $zone)->format('Y-m-d H:i:s'),
                'check_out_at' => $row->check_out_at ? $shift($row->check_out_at, $zone)->format('Y-m-d H:i:s') : null,
            ]);

            $record = AttendanceRecord::query()->find($row->id);
            $employee = User::query()->find($row->user_id);

            if ($record && $employee) {
                app(AttendanceService::class)->measure($record, $employee);
                $record->timestamps = false;
                $record->save();
            }
        }
    }
};
