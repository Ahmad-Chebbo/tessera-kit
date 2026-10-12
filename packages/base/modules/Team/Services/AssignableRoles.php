<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use Illuminate\Support\Collection;

class AssignableRoles
{
    /**
     * The actor's tenant roles, plus shared roles the tenant has no copy of. Platform Admins manage every
     * tenant, so only another Admin may hand that role out.
     *
     * @return Collection<int, Role>
     */
    public function models(User $actor): Collection
    {
        $own = Role::query()->ownedBy($actor->tenant_id)->orderBy('name')->get();

        $shared = Role::query()
            ->whereNull('tenant_id')
            ->whereNotIn('name', $own->pluck('name'))
            ->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('name', '!=', 'Admin'))
            ->orderBy('name')
            ->get();

        return $own->concat($shared)->sortBy('name')->values();
    }

    /**
     * @return list<string>
     */
    public function for(User $actor): array
    {
        return $this->models($actor)->pluck('name')->all();
    }
}
