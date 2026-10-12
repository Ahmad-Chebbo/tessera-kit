<?php

namespace App\Modules\Team\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Team\Actions\CreateEmployeeAction;
use App\Modules\Team\Actions\RemoveEmployeeAction;
use App\Modules\Team\Actions\UpdateEmployeeAction;
use App\Modules\Team\Http\Requests\StoreUserRequest;
use App\Modules\Team\Http\Requests\UpdateUserRequest;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\AssignableRoles;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Tessera\Core\Contracts\BranchScope;

class UserController extends Controller
{
    public function __construct(
        private BranchScope $access,
        private TeamFeatures $features,
        private AssignableRoles $assignableRoles,
        private CreateEmployeeAction $createEmployee,
        private UpdateEmployeeAction $updateEmployee,
        private RemoveEmployeeAction $removeEmployee,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('view users')) {
            abort(403);
        }

        $query = User::query()
            ->where('company_id', $user->company_id)
            ->with(['roles:id,name', ...($this->features->branches() ? ['branch:id,name'] : []), ...($this->features->employeeProfiles() ? ['employeeProfile'] : [])])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->when($this->features->employeeProfiles(), fn ($q) => $q->orWhereHas('employeeProfile', fn ($profile) => $profile
                        ->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('job_title', 'like', "%{$search}%")));
            });
        }

        $this->access->scope($query, $user, $request->input('branch_id'));

        if ($request->filled('role')) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $request->input('role')));
        }

        $users = $query->paginate(20)->withQueryString();

        $branches = $this->access->branches($user);

        $roles = $this->assignableRoles->models($request->user())->map->only(['id', 'name'])->values();

        return Inertia::render('Users/Index', [
            'features' => $this->features->toArray(),
            'users' => $users,
            'branches' => $branches,
            'roles' => $roles,
            'filters' => [...$request->only(['search', 'role']), 'branch_id' => $this->access->canSeeAll($user) ? $this->access->effective($user, $request->input('branch_id')) : null],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('create users')) {
            abort(403);
        }

        $branches = $this->access->branches($user);

        $roles = $this->assignableRoles->models($request->user())->map->only(['id', 'name'])->values();

        return Inertia::render('Users/Create', [
            'features' => $this->features->toArray(),
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        try {
            $this->createEmployee->execute($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(Request $request, User $user): Response
    {
        $actor = $request->user();

        if (! $actor->can('view users') || $user->company_id !== $actor->company_id) {
            abort(403);
        }

        $this->access->ensure($actor, $user->branch_id);

        $user->load(['roles:id,name', ...($this->features->branches() ? ['branch:id,name'] : []), ...($this->features->employeeProfiles() ? ['employeeProfile'] : [])]);

        $activity = ActivityLog::query()
            ->where('company_id', $actor->company_id)
            ->where('user_id', $user->id)
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (ActivityLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'event_label' => $log->eventLabel(),
                'description' => $log->description,
                'user_id' => $log->user_id,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('Users/Show', [
            'features' => $this->features->toArray(),
            'employee' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'branch' => $user->branch ? ['id' => $user->branch->id, 'name' => $user->branch->name] : null,
                'role' => $user->roles->first()?->name,
                'profile' => $user->employeeProfile?->only([...EmployeeProfile::EDITABLE]),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            'activity' => $activity,
            'can' => [
                'edit' => $actor->can('edit users'),
            ],
        ]);
    }

    public function edit(Request $request, User $user): Response
    {
        $currentUser = $request->user();

        if (! $currentUser->can('edit users')) {
            abort(403);
        }

        if ($user->company_id !== $currentUser->company_id) {
            abort(403);
        }

        $this->access->ensure($currentUser, $user->branch_id);

        $branches = $this->access->branches($currentUser);

        if ($this->features->employeeProfiles()) {
            $user->load('employeeProfile');
        }

        $roles = $this->assignableRoles->models($currentUser)->map->only(['id', 'name'])->values();

        return Inertia::render('Users/Edit', [
            'features' => $this->features->toArray(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'is_active' => $user->is_active,
                'role' => $user->roles->first()?->name,
                'profile' => $user->employeeProfile?->only(EmployeeProfile::EDITABLE),
            ],
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        abort_unless($user->company_id === $currentUser->company_id, 403);
        $this->access->ensure($currentUser, $user->branch_id);

        $this->updateEmployee->execute($currentUser, $user, $request->validated());

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();

        if (! $currentUser->can('delete users')) {
            abort(403);
        }

        if ($user->company_id !== $currentUser->company_id) {
            abort(403);
        }

        $this->access->ensure($currentUser, $user->branch_id);

        try {
            $this->removeEmployee->execute($currentUser, $user);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('users.index')->with('error', $e->getMessage());
        }

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }
}
