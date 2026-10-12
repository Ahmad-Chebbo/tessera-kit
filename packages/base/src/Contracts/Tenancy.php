<?php

namespace Tessera\Core\Contracts;

use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * How users, roles and the records around them belong to a tenant. The Tenant module answers; without it there is no
 * tenant column anywhere and every user belongs to the same, unnamed organisation.
 */
interface Tenancy
{
    /**
     * Whether tables have a tenant column at all.
     */
    public function available(): bool;

    /**
     * Limits a query to one tenant's rows.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query, ?int $tenantId, string $column = 'tenant_id'): Builder;

    /**
     * Limits a uniqueness rule to one tenant, the way scope() does for a query.
     */
    public function scopeRule(Unique $rule, ?int $tenantId, string $column = 'tenant_id'): Unique;

    /**
     * The attributes that put a new row in a tenant: empty when there is no tenant column.
     *
     * @return array<string, int|null>
     */
    public function attributes(?int $tenantId): array;

    /**
     * Whether two records belong to the same tenant. Without tenants they always do.
     */
    public function sameTenant(Model $first, Model $second): bool;

    /**
     * Whether everyone uses the same roles, so the people who edit them are editing the roles of the whole project. With
     * many tenants the shared roles are only templates that each tenant copies.
     */
    public function sharesRoles(): bool;

    /**
     * The tenant that owns the actor's roles, or null when roles are shared by everyone: always the case in a project
     * with a single business.
     */
    public function roleOwner(User $actor): ?int;
}
