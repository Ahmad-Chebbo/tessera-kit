<?php

namespace App\Modules\Leave\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Leave\Actions\CancelLeaveAction;
use App\Modules\Leave\Actions\DecideLeaveAction;
use App\Modules\Leave\Actions\RequestLeaveAction;
use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Leave\Http\Requests\StoreHolidayRequest;
use App\Modules\Leave\Http\Requests\StoreLeaveRequestRequest;
use App\Modules\Leave\Http\Requests\StoreLeaveTypeRequest;
use App\Modules\Leave\Models\Holiday;
use App\Modules\Leave\Models\LeaveRequest;
use App\Modules\Leave\Models\LeaveType;
use App\Modules\Leave\Services\LeaveApprovals;
use App\Modules\Leave\Services\LeaveBalances;
use App\Modules\Leave\Services\LeaveDays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(private LeaveBalances $balances, private LeaveApprovals $approvals) {}

    public function index(Request $request, LeaveDays $days): Response
    {
        $actor = $request->user();
        $today = now()->toDateString();

        $approvable = $actor->can('approve leave')
            ? LeaveRequest::query()
                ->with(['user:id,name', 'type:id,name'])
                ->where('tenant_id', $actor->tenant_id)
                ->where('status', LeaveStatus::Pending->value)
                ->oldest()
                ->get()
                ->filter(fn (LeaveRequest $leave): bool => $this->approvals->canDecide($actor, $leave))
                ->map(fn (LeaveRequest $leave): array => $this->present($leave, true))
                ->values()
            : collect();

        return Inertia::render('Leave/Index', [
            'balances' => $this->balances->for($actor, now()->year),
            'requests' => LeaveRequest::query()
                ->with(['type:id,name', 'decider:id,name'])
                ->where('user_id', $actor->id)
                ->latest('starts_on')
                ->limit(50)
                ->get()
                ->map(fn (LeaveRequest $leave): array => $this->present($leave)),
            'approvals' => $approvable,
            'away' => LeaveRequest::query()
                ->with(['user:id,name', 'type:id,name'])
                ->where('tenant_id', $actor->tenant_id)
                ->where('status', LeaveStatus::Approved->value)
                ->where('ends_on', '>=', $today)
                ->where('starts_on', '<=', now()->addDays(30)->toDateString())
                ->orderBy('starts_on')
                ->get()
                ->map(fn (LeaveRequest $leave): array => $this->present($leave, true)),
            'types' => LeaveType::query()->where('tenant_id', $actor->tenant_id)->withCount('requests')->orderBy('name')->get(),
            'holidays' => Holiday::query()->where('tenant_id', $actor->tenant_id)->where('date', '>=', now()->startOfYear()->toDateString())->orderBy('date')->get(['id', 'date', 'name']),
            'weekend' => $days->weekendNumbers(),
            'can' => [
                'approve' => $actor->can('approve leave'),
                'manage' => $actor->can('manage leave'),
            ],
        ]);
    }

    public function store(StoreLeaveRequestRequest $request, RequestLeaveAction $action): RedirectResponse
    {
        $action->execute($request->user(), $request->validated());

        return back()->with('success', 'Leave requested.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, CancelLeaveAction $action): RedirectResponse
    {
        $action->execute($request->user(), $leaveRequest);

        return back()->with('success', 'Leave cancelled.');
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, DecideLeaveAction $action): RedirectResponse
    {
        $action->execute($request->user(), $leaveRequest, true, $this->note($request));

        return back()->with('success', 'Leave approved.');
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, DecideLeaveAction $action): RedirectResponse
    {
        $action->execute($request->user(), $leaveRequest, false, $this->note($request));

        return back()->with('success', 'Leave rejected.');
    }

    public function storeType(StoreLeaveTypeRequest $request): RedirectResponse
    {
        LeaveType::create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Leave type created.');
    }

    public function updateType(StoreLeaveTypeRequest $request, LeaveType $type): RedirectResponse
    {
        $this->ensureSameTenant($request, $type->tenant_id);
        $type->update($request->validated());

        return back()->with('success', 'Leave type updated.');
    }

    public function destroyType(Request $request, LeaveType $type): RedirectResponse
    {
        $this->ensureSameTenant($request, $type->tenant_id);

        if ($type->requests()->exists()) {
            return back()->withErrors(['type' => 'This type has requests. Switch it off instead of deleting it.']);
        }

        $type->delete();

        return back()->with('success', 'Leave type deleted.');
    }

    public function storeHoliday(StoreHolidayRequest $request): RedirectResponse
    {
        Holiday::create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Holiday added.');
    }

    public function destroyHoliday(Request $request, Holiday $holiday): RedirectResponse
    {
        $this->ensureSameTenant($request, $holiday->tenant_id);
        $holiday->delete();

        return back()->with('success', 'Holiday removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeaveRequest $leave, bool $withEmployee = false): array
    {
        return [
            'id' => $leave->id,
            'type' => $leave->type?->name,
            'starts_on' => $leave->starts_on->toDateString(),
            'ends_on' => $leave->ends_on->toDateString(),
            'days' => $leave->days,
            'reason' => $leave->reason,
            'status' => $leave->status->value,
            'decided_by' => $leave->decider?->name,
            'decision_note' => $leave->decision_note,
            'can_cancel' => $leave->user_id === request()->user()->id && ($leave->status === LeaveStatus::Pending || ($leave->status === LeaveStatus::Approved && $leave->starts_on->toDateString() > now()->toDateString())),
            ...($withEmployee ? ['employee' => $leave->user?->name] : []),
        ];
    }

    private function note(Request $request): ?string
    {
        return $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
    }

    private function ensureSameTenant(Request $request, int $tenantId): void
    {
        abort_unless($request->user()->can('manage leave') && $request->user()->tenant_id === $tenantId, 403);
    }
}
