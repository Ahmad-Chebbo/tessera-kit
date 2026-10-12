<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Team\Models\Role;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Collection;

class RoleTemplates
{
    /**
     * @var list<string>
     */
    public const NAMES = ['Owner', 'Manager', 'Cashier', 'Stock Keeper'];

    /**
     * Give a tenant its own copy of each standard role, with the template's permissions.
     *
     * @return Collection<string, Role>
     */
    public function provision(Tenant $tenant): Collection
    {
        $templates = Role::query()->whereNull('tenant_id')->whereIn('name', self::NAMES)->with('permissions')->get()->keyBy('name');

        return collect(self::NAMES)->mapWithKeys(function (string $name) use ($tenant, $templates): array {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'tenant_id' => $tenant->id]);

            if ($role->wasRecentlyCreated && $templates->has($name)) {
                $role->syncPermissions($templates[$name]->permissions);
            }

            return [$name => $role];
        });
    }
}
