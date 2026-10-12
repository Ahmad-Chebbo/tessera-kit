<?php

namespace App\Modules\Company\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Team\Models\Role;
use Illuminate\Support\Collection;

class RoleTemplates
{
    /**
     * @var list<string>
     */
    public const NAMES = ['Owner', 'Manager', 'Cashier', 'Stock Keeper'];

    /**
     * Give a company its own copy of each standard role, with the template's permissions.
     *
     * @return Collection<string, Role>
     */
    public function provision(Company $company): Collection
    {
        $templates = Role::query()->whereNull('company_id')->whereIn('name', self::NAMES)->with('permissions')->get()->keyBy('name');

        return collect(self::NAMES)->mapWithKeys(function (string $name) use ($company, $templates): array {
            $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web', 'company_id' => $company->id]);

            if ($role->wasRecentlyCreated && $templates->has($name)) {
                $role->syncPermissions($templates[$name]->permissions);
            }

            return [$name => $role];
        });
    }
}
