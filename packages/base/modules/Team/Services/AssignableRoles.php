<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use Illuminate\Support\Collection;
use Tessera\Core\Contracts\Tenancy;

class AssignableRoles
{
    public function __construct(private readonly Tenancy $tenancy) {}

    /**
     * The roles the actor's tenant owns, plus shared roles the tenant has no copy of; with one business that is every
     * role. Platform Admins manage every tenant, so only another Admin may hand that role out.
     *
     * @return Collection<int, Role>
     */
    public function models(User $actor): Collection
    {
        $hideAdmin = fn ($query) => $query->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('name', '!=', 'Admin'));

        $own = $hideAdmin(Role::query()->ownedBy($this->tenancy->roleOwner($actor)))->orderBy('name')->get();

        if (! $this->tenancy->available()) {
            return $own->values();
        }

        $shared = $hideAdmin(Role::query()->whereNull('tenant_id')->whereNotIn('name', $own->pluck('name')))->orderBy('name')->get();

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
