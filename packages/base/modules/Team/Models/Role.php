<?php

namespace App\Modules\Team\Models;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;
use Tessera\Core\Contracts\Tenancy;

/**
 * A role, owned by one tenant when the Subscription module manages many. Roles without a tenant are the platform Admin
 * role and the templates new tenants are provisioned from; in a project with one business, or none, they are the only
 * roles.
 */
class Role extends SpatieRole
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, ?int $tenantId): void
    {
        app(Tenancy::class)->scope($query, $tenantId);
    }

    /**
     * The role a tenant uses under a name: its own role, or the shared one when it has none.
     */
    public static function resolveFor(?int $tenantId, string $name): ?self
    {
        $query = static::query()->where('name', $name);

        if (! app(Tenancy::class)->available()) {
            return $query->first();
        }

        return $query
            ->where(fn (Builder $query) => $query->whereNull('tenant_id')->when($tenantId, fn (Builder $query) => $query->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('tenant_id is null')
            ->first();
    }

    /**
     * Name lookups prefer the signed-in user's tenant role over a shared role of the same name.
     *
     * @param  array<string, mixed>  $params
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $query = static::query()->where($params);

        if (! app(Tenancy::class)->available()) {
            return $query->first();
        }

        $tenantId = (int) auth()->user()?->tenant_id;

        return $query
            ->where(fn (Builder $query) => $query->whereNull('tenant_id')->when($tenantId, fn (Builder $query) => $query->orWhere('tenant_id', $tenantId)))
            ->orderByRaw('tenant_id is null')
            ->first();
    }
}
