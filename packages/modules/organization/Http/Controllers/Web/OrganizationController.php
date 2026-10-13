<?php

namespace App\Modules\Organization\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Organization\Http\Requests\AssignEmployeeRequest;
use App\Modules\Organization\Http\Requests\StoreDepartmentRequest;
use App\Modules\Organization\Http\Requests\StorePositionRequest;
use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\EmployeeAssignment;
use App\Modules\Organization\Models\Position;
use App\Modules\Team\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $assignments = EmployeeAssignment::query()->where('tenant_id', $actor->tenant_id)->get()->keyBy('user_id');

        return Inertia::render('Organization/Index', [
            'departments' => Department::query()
                ->where('tenant_id', $actor->tenant_id)
                ->withCount(['assignments', 'children'])
                ->with('head:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'parent_id' => $department->parent_id,
                    'head_id' => $department->head_id,
                    'head' => $department->head?->name,
                    'is_active' => $department->is_active,
                    'employees_count' => $department->assignments_count,
                    'children_count' => $department->children_count,
                ]),
            'positions' => Position::query()
                ->where('tenant_id', $actor->tenant_id)
                ->withCount('assignments')
                ->with('department:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (Position $position): array => [
                    'id' => $position->id,
                    'name' => $position->name,
                    'department_id' => $position->department_id,
                    'department' => $position->department?->name,
                    'is_active' => $position->is_active,
                    'employees_count' => $position->assignments_count,
                ]),
            'employees' => User::query()
                ->where('tenant_id', $actor->tenant_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'department_id' => $assignments[$user->id]->department_id ?? null,
                    'position_id' => $assignments[$user->id]->position_id ?? null,
                    'manager_id' => $assignments[$user->id]->manager_id ?? null,
                ]),
            'can' => ['manage' => $actor->can('manage organization')],
        ]);
    }

    public function storeDepartment(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Department created.');
    }

    public function updateDepartment(StoreDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->ensureSameTenant($request, $department->tenant_id);
        $department->update($request->validated());

        return back()->with('success', 'Department updated.');
    }

    public function destroyDepartment(Request $request, Department $department): RedirectResponse
    {
        $this->ensureSameTenant($request, $department->tenant_id);

        if ($department->children()->exists()) {
            return back()->withErrors(['department' => 'Move or delete the departments under it first.']);
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }

    public function storePosition(StorePositionRequest $request): RedirectResponse
    {
        Position::create([...$request->validated(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Position created.');
    }

    public function updatePosition(StorePositionRequest $request, Position $position): RedirectResponse
    {
        $this->ensureSameTenant($request, $position->tenant_id);
        $position->update($request->validated());

        return back()->with('success', 'Position updated.');
    }

    public function destroyPosition(Request $request, Position $position): RedirectResponse
    {
        $this->ensureSameTenant($request, $position->tenant_id);
        $position->delete();

        return back()->with('success', 'Position deleted.');
    }

    public function assign(AssignEmployeeRequest $request, User $employee): RedirectResponse
    {
        $this->ensureSameTenant($request, $employee->tenant_id);

        EmployeeAssignment::updateOrCreate(
            ['user_id' => $employee->id],
            [...$request->validated(), 'tenant_id' => $employee->tenant_id],
        );

        return back()->with('success', 'Employee updated.');
    }

    private function ensureSameTenant(Request $request, ?int $tenantId): void
    {
        abort_unless($request->user()->can('manage organization') && $request->user()->tenant_id === $tenantId, 403);
    }
}
