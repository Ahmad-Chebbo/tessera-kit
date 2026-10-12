<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tessera\Engine\ModuleRegistry;

/**
 * Makes the database match what the enabled modules declare: every permission exists, and the shared template roles
 * hold their default permissions. Permissions created by this run are also given to the matching role of every
 * company, so a freshly installed module works for existing companies; permissions that already existed are never
 * handed out again, which keeps roles a company has trimmed trimmed.
 */
class PermissionSynchronizer
{
    public function __construct(private readonly ModuleRegistry $registry) {}

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
            $template = Role::query()->firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'company_id' => null]);
            $template->givePermissionTo($permissions);
            $roles[] = $roleName;

            if ($fresh = array_values(array_intersect($permissions, $created))) {
                Role::query()->where('name', $roleName)->whereNotNull('company_id')->each(fn (Role $role) => $role->givePermissionTo($fresh));
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return ['created' => $created, 'roles' => $roles];
    }
}
