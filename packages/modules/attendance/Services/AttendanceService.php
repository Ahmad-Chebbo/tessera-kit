<?php

namespace App\Modules\Attendance\Services;

use App\Modules\Attendance\Models\AttendanceExcuse;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AttendanceService
{
    public function __construct(private ActivityLogger $activity, private BranchAccess $access) {}

    public function current(User $user): ?AttendanceRecord
    {
        return AttendanceRecord::query()->where('open_user_id', $user->id)->first();
    }

    public function timezone(User $user): string
    {
        return $user->company?->timezone ?: config('app.timezone');
    }

    public function checkIn(User $user): AttendanceRecord
    {
        $record = new AttendanceRecord([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'open_user_id' => $user->id,
            'check_in_at' => now(),
            'source' => AttendanceRecord::SELF,
        ]);

        $this->measure($record, $user);

        try {
            $record->save();
        } catch (QueryException) {
            throw new InvalidArgumentException('You are already checked in.');
        }

        $this->activity->record(ActivityEvent::CheckedIn, 'Checked in', $record, ['late_minutes' => $record->late_minutes], $user);

        return $record;
    }

    public function checkOut(User $user): AttendanceRecord
    {
        $record = $this->current($user) ?? throw new InvalidArgumentException('You are not checked in.');

        $record->check_out_at = now();
        $record->open_user_id = null;
        $this->measure($record, $user);
        $record->save();

        $this->activity->record(ActivityEvent::CheckedOut, 'Checked out', $record, ['worked_minutes' => $record->worked_minutes], $user);

        return $record;
    }

    /**
     * Create or correct a record on behalf of an employee.
     */
    public function save(User $employee, User $actor, CarbonInterface $checkIn, ?CarbonInterface $checkOut, ?string $notes, ?AttendanceRecord $record = null): AttendanceRecord
    {
        $record ??= new AttendanceRecord(['company_id' => $employee->company_id, 'user_id' => $employee->id, 'created_by' => $actor->id]);

        $storedZone = config('app.timezone');

        $record->fill([
            'check_in_at' => CarbonImmutable::instance($checkIn)->setTimezone($storedZone),
            'check_out_at' => $checkOut ? CarbonImmutable::instance($checkOut)->setTimezone($storedZone) : null,
            'open_user_id' => $checkOut === null ? $employee->id : null,
            'source' => AttendanceRecord::MANAGER,
            'notes' => $notes,
        ]);

        $this->measure($record, $employee);

        try {
            $record->save();
        } catch (QueryException) {
            throw new InvalidArgumentException("{$employee->name} already has an open record.");
        }

        $this->activity->record(ActivityEvent::AttendanceAdjusted, "Adjusted attendance for {$employee->name}", $record, [], $actor);

        return $record;
    }

    /**
     * Fill the derived fields: work date, matched shift, lateness, early leave and worked time.
     */
    /**
     * Adds or corrects an attendance record for an employee of the actor's company, with times in the company's timezone.
     *
     * @throws InvalidArgumentException when the times do not make a valid record.
     */
    public function saveFor(User $actor, int $employeeId, string $checkIn, ?string $checkOut, ?string $notes, ?AttendanceRecord $record = null): AttendanceRecord
    {
        $employee = User::query()->where('company_id', $actor->company_id)->findOrFail($employeeId);
        $this->access->ensure($actor, $employee->branch_id);
        $timezone = $this->timezone($actor);

        return $this->save(
            $employee,
            $actor,
            CarbonImmutable::parse($checkIn, $timezone),
            $checkOut ? CarbonImmutable::parse($checkOut, $timezone) : null,
            $notes,
            $record,
        );
    }

    /**
     * Excuses an employee's absence on a past day, or changes the reason of an existing excuse.
     */
    public function excuse(User $actor, int $employeeId, string $date, string $reason): AttendanceExcuse
    {
        $this->access->ensure($actor, User::query()->whereKey($employeeId)->value('branch_id'));

        return AttendanceExcuse::updateOrCreate(
            ['user_id' => $employeeId, 'date' => $date],
            ['company_id' => $actor->company_id, 'reason' => $reason, 'created_by' => $actor->id],
        );
    }

    public function measure(AttendanceRecord $record, User $employee): void
    {
        $timezone = $this->timezone($employee);
        $checkIn = CarbonImmutable::instance($record->check_in_at)->setTimezone($timezone);
        $workDate = $checkIn->toDateString();

        $assignment = ShiftAssignment::query()
            ->with('template')
            ->where('user_id', $employee->id)
            ->where('date', $workDate)
            ->get()
            ->sortBy(fn (ShiftAssignment $candidate): int => abs($this->shiftStart($candidate, $timezone)->diffInMinutes($checkIn, false)))
            ->first();

        $record->work_date = $workDate;
        $record->shift_template_id = $assignment?->shift_template_id;
        $record->late_minutes = 0;
        $record->early_leave_minutes = 0;
        $record->worked_minutes = 0;

        if ($assignment) {
            $start = $this->shiftStart($assignment, $timezone);
            $record->late_minutes = max(0, (int) $start->diffInMinutes($checkIn, false));
        }

        if ($record->check_out_at) {
            $checkOut = CarbonImmutable::instance($record->check_out_at);
            $record->worked_minutes = max(0, (int) $checkIn->diffInMinutes($checkOut, false));

            if ($assignment) {
                $end = $this->shiftStart($assignment, $timezone)->addMinutes($assignment->template->durationMinutes());
                $record->early_leave_minutes = max(0, (int) $checkOut->diffInMinutes($end, false));
            }
        }
    }

    /**
     * Assigned shift days that have passed with no attendance record. Days with an excuse are flagged, not hidden.
     *
     * @return Collection<int, array{user_id: int, user: string, date: string, shift: string, excused: bool, reason: ?string, excuse_id: ?int}>
     */
    public function absences(int $companyId, CarbonInterface $from, CarbonInterface $to, ?int $userId = null, ?int $branchId = null): Collection
    {
        $lastDay = CarbonImmutable::yesterday()->min($to);

        if ($from->gt($lastDay)) {
            return collect();
        }

        $assignments = ShiftAssignment::query()
            ->with(['user:id,name', 'template:id,name'])
            ->where('company_id', $companyId)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($branchId !== null, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('branch_id', $branchId)))
            ->whereBetween('date', [$from->toDateString(), $lastDay->toDateString()])
            ->get();

        $present = AttendanceRecord::query()
            ->where('company_id', $companyId)
            ->whereBetween('work_date', [$from->toDateString(), $lastDay->toDateString()])
            ->get(['user_id', 'work_date'])
            ->mapWithKeys(fn (AttendanceRecord $record): array => [$record->user_id.'|'.$record->work_date => true]);

        $excuses = AttendanceExcuse::query()
            ->where('company_id', $companyId)
            ->whereBetween('date', [$from->toDateString(), $lastDay->toDateString()])
            ->get()
            ->keyBy(fn (AttendanceExcuse $excuse): string => $excuse->user_id.'|'.$excuse->date);

        return $assignments
            ->reject(fn (ShiftAssignment $assignment): bool => isset($present[$assignment->user_id.'|'.$assignment->date]))
            ->groupBy(fn (ShiftAssignment $assignment): string => $assignment->user_id.'|'.$assignment->date)
            ->map(function (Collection $group, string $key) use ($excuses): array {
                $first = $group->first();
                $excuse = $excuses->get($key);

                return [
                    'user_id' => $first->user_id,
                    'user' => $first->user?->name ?? '',
                    'date' => $first->date,
                    'shift' => $group->map(fn (ShiftAssignment $assignment): string => (string) $assignment->template?->name)->implode(', '),
                    'excused' => $excuse !== null,
                    'reason' => $excuse?->reason,
                    'excuse_id' => $excuse?->id,
                ];
            })
            ->sortBy([['date', 'desc'], ['user', 'asc']])
            ->values();
    }

    /**
     * Hours, lateness and absences per employee, plus a per-day series for the charts.
     *
     * @return array{employees: list<array<string, mixed>>, daily: list<array{date: string, headcount: int, hours: float}>, totals: array<string, mixed>}
     */
    public function report(int $companyId, CarbonInterface $from, CarbonInterface $to, ?int $userId = null, ?int $branchId = null): array
    {
        $records = AttendanceRecord::query()
            ->with('user:id,name')
            ->where('company_id', $companyId)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->when($branchId !== null, fn ($query) => $query->whereHas('user', fn ($user) => $user->where('branch_id', $branchId)))
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $absences = $this->absences($companyId, $from, $to, $userId, $branchId);

        $employees = $records->groupBy('user_id')->map(function (Collection $group, int $id) use ($absences): array {
            $mine = $absences->where('user_id', $id);

            return [
                'user_id' => $id,
                'name' => $group->first()->user?->name ?? '',
                'days_present' => $group->pluck('work_date')->unique()->count(),
                'worked_minutes' => (int) $group->sum('worked_minutes'),
                'late_count' => $group->where('late_minutes', '>', 0)->count(),
                'late_minutes' => (int) $group->sum('late_minutes'),
                'absences' => $mine->where('excused', false)->count(),
                'excused' => $mine->where('excused', true)->count(),
            ];
        });

        foreach ($absences->groupBy('user_id') as $id => $rows) {
            if (! $employees->has($id)) {
                $employees->put($id, [
                    'user_id' => $id, 'name' => $rows->first()['user'], 'days_present' => 0, 'worked_minutes' => 0,
                    'late_count' => 0, 'late_minutes' => 0,
                    'absences' => $rows->where('excused', false)->count(), 'excused' => $rows->where('excused', true)->count(),
                ]);
            }
        }

        $daily = $records->groupBy('work_date')->map(fn (Collection $group, string $date): array => [
            'date' => $date,
            'headcount' => $group->pluck('user_id')->unique()->count(),
            'hours' => round($group->sum('worked_minutes') / 60, 1),
        ])->sortKeys()->values()->all();

        return [
            'employees' => $employees->sortBy('name')->values()->all(),
            'daily' => $daily,
            'totals' => [
                'worked_minutes' => (int) $records->sum('worked_minutes'),
                'late_count' => $records->where('late_minutes', '>', 0)->count(),
                'absences' => $absences->where('excused', false)->count(),
                'excused' => $absences->where('excused', true)->count(),
                'records' => $records->count(),
            ],
        ];
    }

    private function shiftStart(ShiftAssignment $assignment, string $timezone): CarbonImmutable
    {
        return CarbonImmutable::parse($assignment->date.' '.$assignment->template->starts_at, $timezone);
    }
}
