<?php

namespace App\Modules\Attendance\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Attendance\Http\Requests\AttendanceFilterRequest;
use App\Modules\Attendance\Http\Requests\StoreAttendanceExcuseRequest;
use App\Modules\Attendance\Http\Requests\StoreAttendanceRecordRequest;
use App\Modules\Attendance\Models\AttendanceExcuse;
use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Attendance\Services\AttendanceRecords;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendance, private BranchAccess $access, private AttendanceRecords $records) {}

    /**
     * @return array<string, mixed>
     */
    private function filtersFor(AttendanceFilterRequest $request, int $daysBack = 30): array
    {
        $actor = $request->user();

        return [
            'user_id' => $request->query('user_id'),
            'branch_id' => $this->access->canSeeAll($actor) ? $this->access->effective($actor, $request->query('branch_id')) : null,
            'date_from' => $request->from($daysBack)->toDateString(),
            'date_to' => $request->to()->toDateString(),
        ];
    }

    private function employeesFor(User $actor): mixed
    {
        return $this->access->scope(User::query()->where('tenant_id', $actor->tenant_id), $actor)->orderBy('name')->get(['id', 'name', 'branch_id']);
    }

    public function index(AttendanceFilterRequest $request): Response
    {
        $actor = $request->user();
        $seesEveryone = $actor->can('view attendance');
        $timezone = $this->attendance->timezone($actor);

        $records = $this->records->visibleTo($actor, $request->from(7), $request->to(), $request->integer('user_id') ?: null, $request->query('branch_id'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (AttendanceRecord $record): array => [
                'id' => $record->id,
                'user' => $record->user ? ['id' => $record->user->id, 'name' => $record->user->name] : null,
                'work_date' => $record->work_date,
                'shift' => $record->shiftTemplate?->name,
                'check_in_at' => $record->check_in_at->toIso8601String(),
                'check_out_at' => $record->check_out_at?->toIso8601String(),
                'check_in_local' => $record->check_in_at->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'check_out_local' => $record->check_out_at?->setTimezone($timezone)->format('Y-m-d\TH:i'),
                'worked_minutes' => $record->worked_minutes,
                'late_minutes' => $record->late_minutes,
                'early_leave_minutes' => $record->early_leave_minutes,
                'source' => $record->source,
                'notes' => $record->notes,
            ]);

        return Inertia::render('Attendance/Index', [
            'records' => $records,
            'employees' => $seesEveryone ? $this->employeesFor($actor) : [],
            'filters' => $this->filtersFor($request, 7),
            'can' => ['manage' => $actor->can('manage attendance'), 'viewAll' => $seesEveryone, 'record' => $actor->can('record attendance')],
        ]);
    }

    public function checkIn(Request $request): RedirectResponse
    {
        try {
            $this->attendance->checkIn($request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Checked in.');
    }

    public function checkOut(Request $request): RedirectResponse
    {
        try {
            $this->attendance->checkOut($request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Checked out.');
    }

    public function store(StoreAttendanceRecordRequest $request): RedirectResponse
    {
        return $this->persist($request, null);
    }

    public function update(StoreAttendanceRecordRequest $request, AttendanceRecord $record): RedirectResponse
    {
        abort_unless($record->tenant_id === $request->user()->tenant_id, 403);
        $this->access->ensure($request->user(), $record->user()->value('branch_id'));

        return $this->persist($request, $record);
    }

    public function destroy(Request $request, AttendanceRecord $record): RedirectResponse
    {
        abort_unless($request->user()->can('manage attendance') && $record->tenant_id === $request->user()->tenant_id, 403);
        $this->access->ensure($request->user(), $record->user()->value('branch_id'));
        $record->delete();

        return back()->with('success', 'Record deleted.');
    }

    public function absences(AttendanceFilterRequest $request): Response
    {
        $actor = $request->user();

        return Inertia::render('Attendance/Absences', [
            'absences' => $this->attendance->absences($actor->tenant_id, $request->from(), $request->to(), $request->integer('user_id') ?: null, $this->access->effective($actor, $request->query('branch_id'))),
            'employees' => $this->employeesFor($actor),
            'filters' => $this->filtersFor($request),
            'can' => ['manage' => $actor->can('manage attendance')],
        ]);
    }

    public function storeExcuse(StoreAttendanceExcuseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->attendance->excuse($request->user(), (int) $data['user_id'], $data['date'], $data['reason']);

        return back()->with('success', 'Absence excused.');
    }

    public function destroyExcuse(Request $request, AttendanceExcuse $excuse): RedirectResponse
    {
        abort_unless($request->user()->can('manage attendance') && $excuse->tenant_id === $request->user()->tenant_id, 403);
        $this->access->ensure($request->user(), $excuse->user()->value('branch_id'));
        $excuse->delete();

        return back()->with('success', 'Excuse removed.');
    }

    public function report(AttendanceFilterRequest $request): Response
    {
        $actor = $request->user();

        return Inertia::render('Attendance/Report', [
            'report' => $this->attendance->report($actor->tenant_id, $request->from(), $request->to(), $request->integer('user_id') ?: null, $this->access->effective($actor, $request->query('branch_id'))),
            'employees' => $this->employeesFor($actor),
            'filters' => $this->filtersFor($request),
        ]);
    }

    private function persist(StoreAttendanceRecordRequest $request, ?AttendanceRecord $record): RedirectResponse
    {
        try {
            $this->attendance->saveFor(
                $request->user(),
                (int) $request->validated('user_id'),
                $request->validated('check_in_at'),
                $request->validated('check_out_at'),
                $request->validated('notes'),
                $record,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Attendance saved.');
    }
}
