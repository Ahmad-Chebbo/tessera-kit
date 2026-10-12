<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tessera\Core\Contracts\Tenancy;
use Tessera\Engine\ModuleRegistry;

/**
 * Makes the database match what the enabled modules declare: every permission exists, and the shared template roles
 * hold their default permissions. Permissions created by this run are also given to the matching role of every
 * tenant, so a freshly installed module works for existing tenants; permissions that already existed are never
 * handed out again, which keeps roles a tenant has trimmed trimmed.
 */
class PermissionSynchronizer
{
    public function __construct(private readonly ModuleRegistry $registry, private readonly Tenancy $tenancy) {}

    /**
     * @return array{created: list<string>, roles: list<string>}
     */
    public function sync(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $created = [];

        foreach ($this->registry->permissions() as $name) {
            if (Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web'])->wasRecentlyCreated) {
                $created[] = $name;
            }
        }

        $roles = [];

        foreach ($this->registry->defaultRolePermissions() as $roleName => $permissions) {
            $template = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web', ...$this->tenancy->attributes(null)]);

            // Roles people edit keep what they were left with: they get the defaults when new, and then only the
            // permissions that did not exist before. Templates nobody edits always carry all of them.
            $template->givePermissionTo($template->wasRecentlyCreated || ! $this->tenancy->sharesRoles() ? $permissions : array_values(array_intersect($permissions, $created)));
            $roles[] = $roleName;

            if ($this->tenancy->available() && ($fresh = array_values(array_intersect($permissions, $created)))) {
                Role::query()->where('name', $roleName)->whereNotNull('tenant_id')->each(fn (Role $role) => $role->givePermissionTo($fresh));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['created' => $created, 'roles' => $roles];
    }
}
