<?php

namespace App\Modules\Team\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Http\Requests\StoreRoleRequest;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Contracts\Tenancy;
use Tessera\Engine\ModuleRegistry;

class RoleController extends Controller
{
    public function __construct(private ActivityLogger $activity, private Tenancy $tenancy) {}

    public function index(Request $request): Response
    {
        $roles = Role::query()
            ->ownedBy($this->tenancy->roleOwner($request->user()))
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'id' => $role->id,
                'name' => $role->name,
                'users_count' => $role->users_count,
                'permissions_count' => $role->permissions_count,
                'is_mine' => $request->user()->roles->contains('id', $role->id),
            ]);

        return Inertia::render('Roles/Index', ['roles' => $roles]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Roles/Edit', [
            'role' => null,
            'groups' => $this->groups($request),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $role = Role::query()->create([
            'name' => $request->validated('name'),
            'guard_name' => 'web',
            ...$this->tenancy->attributes($this->tenancy->roleOwner($request->user())),
        ]);

        $role->syncPermissions($request->validated('permissions'));
        $this->activity->record(ActivityEvent::RoleCreated, "Created role {$role->name}", null, ['role' => $role->name]);

        return redirect()->route('roles.index')->with('success', 'Role created.');
    }

    public function edit(Request $request, Role $role): Response
    {
        $this->ensureOwned($request, $role);

        return Inertia::render('Roles/Edit', [
            'role' => ['id' => $role->id, 'name' => $role->name, 'permissions' => $role->permissions->pluck('name'), 'is_mine' => $request->user()->roles->contains('id', $role->id)],
            'groups' => $this->groups($request, $role),
        ]);
    }

    public function update(StoreRoleRequest $request, Role $role): RedirectResponse
    {
        $this->ensureOwned($request, $role);

        $kept = $role->permissions->pluck('name')->diff($request->grantablePermissions());
        $role->update(['name' => $request->validated('name')]);
        $role->syncPermissions($kept->merge($request->validated('permissions'))->unique()->values()->all());

        $this->activity->record(ActivityEvent::RoleUpdated, "Updated role {$role->name}", null, ['role' => $role->name]);

        return redirect()->route('roles.index')->with('success', 'Role updated.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->ensureOwned($request, $role);

        if ($role->users()->exists()) {
            return back()->with('error', 'Move the employees on this role to another role first.');
        }

        $name = $role->name;
        $role->delete();
        $this->activity->record(ActivityEvent::RoleDeleted, "Deleted role {$name}", null, ['role' => $name]);

        return back()->with('success', 'Role deleted.');
    }

    private function ensureOwned(Request $request, Role $role): void
    {
        abort_unless($request->user()->can('manage roles') && $role->tenant_id === $this->tenancy->roleOwner($request->user()), 403);
    }

    /**
     * Permission groups for the editor. Permissions the editor does not hold are listed but cannot be toggled.
     *
     * @return list<array{key: string, permissions: list<array{name: string, grantable: bool}>}>
     */
    private function groups(Request $request, ?Role $role = null): array
    {
        $held = $request->user()->getAllPermissions()->pluck('name');
        $known = Permission::query()->pluck('name')->reject(fn (string $name): bool => $name === 'manage tenants');
        $listed = collect(app(ModuleRegistry::class)->permissionGroups())->flatten();
        $groups = app(ModuleRegistry::class)->permissionGroups();
        $groups['other'] = $known->diff($listed)->values()->all();

        return collect($groups)
            ->map(fn (array $names, string $key): array => [
                'key' => $key,
                'permissions' => collect($names)->filter(fn (string $name): bool => $known->contains($name))->map(fn (string $name): array => [
                    'name' => $name,
                    'grantable' => $held->contains($name),
                ])->values()->all(),
            ])
            ->filter(fn (array $group): bool => $group['permissions'] !== [])
            ->values()
            ->all();
    }
}
