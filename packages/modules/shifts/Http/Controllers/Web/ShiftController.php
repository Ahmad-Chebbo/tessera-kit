<?php

namespace App\Modules\Shifts\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Shifts\Actions\AssignShiftAction;
use App\Modules\Shifts\Actions\CreateShiftCycleAction;
use App\Modules\Shifts\Http\Requests\StoreShiftAssignmentRequest;
use App\Modules\Shifts\Http\Requests\StoreShiftCycleRequest;
use App\Modules\Shifts\Http\Requests\StoreShiftTemplateRequest;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftCycle;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use App\Modules\Tenant\Services\BranchAccess;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(private ActivityLogger $activity, private BranchAccess $access) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        $weekStart = $this->weekStart($request->query('week'));
        $weekEnd = $weekStart->addDays(6);
        $branch = $request->query('branch_id');

        return Inertia::render('Shifts/Index', [
            'weekStart' => $weekStart->toDateString(),
            'days' => collect(range(0, 6))->map(fn (int $offset): string => $weekStart->addDays($offset)->toDateString()),
            'templates' => ShiftTemplate::query()->where('tenant_id', $actor->tenant_id)->orderBy('starts_at')->get(),
            'employees' => $this->access->scope(User::query()->where('tenant_id', $actor->tenant_id)->where('is_active', true), $actor, $branch)->orderBy('name')->get(['id', 'name']),
            'branchId' => $this->access->canSeeAll($actor) ? $this->access->effective($actor, $branch) : null,
            'assignments' => ShiftAssignment::query()
                ->where('tenant_id', $actor->tenant_id)
                ->tap(fn ($query) => $this->access->scopeThroughUser($query, $actor, $branch))
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get()
                ->map(fn (ShiftAssignment $assignment): array => [
                    'id' => $assignment->id,
                    'user_id' => $assignment->user_id,
                    'shift_template_id' => $assignment->shift_template_id,
                    'date' => $assignment->date,
                    'from_cycle' => $assignment->shift_cycle_id !== null,
                ]),
            'cycles' => ShiftCycle::query()
                ->where('tenant_id', $actor->tenant_id)
                ->tap(fn ($query) => $this->access->scopeThroughUser($query, $actor, $branch))
                ->with(['user:id,name', 'template:id,name'])
                ->latest('id')
                ->get()
                ->map(fn (ShiftCycle $cycle): array => [
                    'id' => $cycle->id,
                    'user' => $cycle->user?->name,
                    'template' => $cycle->template?->name,
                    'weekdays' => $cycle->weekdays,
                    'starts_on' => $cycle->starts_on->toDateString(),
                    'ends_on' => $cycle->ends_on?->toDateString(),
                ]),
            'can' => ['manage' => $actor->can('manage shifts')],
        ]);
    }

    public function storeTemplate(StoreShiftTemplateRequest $request): RedirectResponse
    {
        ShiftTemplate::create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Shift created.');
    }

    public function updateTemplate(StoreShiftTemplateRequest $request, ShiftTemplate $template): RedirectResponse
    {
        $this->ensureSameTenant($request, $template->tenant_id);
        $template->update($request->validated());

        return back()->with('success', 'Shift updated.');
    }

    public function destroyTemplate(Request $request, ShiftTemplate $template): RedirectResponse
    {
        $this->ensureSameTenant($request, $template->tenant_id);
        $template->delete();

        return back()->with('success', 'Shift deleted.');
    }

    public function storeAssignment(StoreShiftAssignmentRequest $request, AssignShiftAction $assign): RedirectResponse
    {
        $assign->execute($request->user(), $request->validated());

        return back()->with('success', 'Employee assigned.');
    }

    public function destroyAssignment(Request $request, ShiftAssignment $assignment): RedirectResponse
    {
        $this->ensureSameTenant($request, $assignment->tenant_id);
        $this->access->ensure($request->user(), $assignment->user()->value('branch_id'));
        $assignment->delete();

        return back()->with('success', 'Assignment removed.');
    }

    public function storeCycle(StoreShiftCycleRequest $request, CreateShiftCycleAction $create): RedirectResponse
    {
        $create->execute($request->user(), $request->validated());

        return back()->with('success', 'Cycle created.');
    }

    public function generateCycle(Request $request, ShiftCycle $cycle): RedirectResponse
    {
        $this->ensureSameTenant($request, $cycle->tenant_id);
        $this->access->ensure($request->user(), $cycle->user()->value('branch_id'));
        $created = app(CreateShiftCycleAction::class)->generateAhead($cycle);

        return back()->with('success', "Generated {$created} assignments.");
    }

    public function destroyCycle(Request $request, ShiftCycle $cycle): RedirectResponse
    {
        $this->ensureSameTenant($request, $cycle->tenant_id);
        $this->access->ensure($request->user(), $cycle->user()->value('branch_id'));
        $cycle->assignments()->where('date', '>=', now()->toDateString())->delete();
        $cycle->delete();

        return back()->with('success', 'Cycle deleted.');
    }

    private function weekStart(mixed $value): CarbonImmutable
    {
        try {
            $date = is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : CarbonImmutable::today();
        } catch (InvalidFormatException) {
            $date = CarbonImmutable::today();
        }

        return $date->startOfWeek(CarbonImmutable::MONDAY);
    }

    private function ensureSameTenant(Request $request, int $tenantId): void
    {
        abort_unless($request->user()->can('manage shifts') && $request->user()->tenant_id === $tenantId, 403);
    }
}
