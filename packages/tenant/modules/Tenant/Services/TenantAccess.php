<?php

namespace App\Modules\Tenant\Services;

use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Tessera\Core\Contracts\Tenancy;
use Tessera\Engine\ModuleRegistry;

/**
 * Puts users, roles and the records around them in a tenant. With the Subscription module there are many tenants and
 * each owns its roles; without it the project has one business and everyone shares the roles.
 */
class TenantAccess implements Tenancy
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    public function available(): bool
    {
        return true;
    }

    public function scope(Builder $query, ?int $tenantId, string $column = 'tenant_id'): Builder
    {
        return $query->where($column, $tenantId);
    }

    public function scopeRule(Unique $rule, ?int $tenantId, string $column = 'tenant_id'): Unique
    {
        return $rule->where($column, $tenantId);
    }

    public function attributes(?int $tenantId): array
    {
        return ['tenant_id' => $tenantId];
    }

    public function sameTenant(Model $first, Model $second): bool
    {
        return $first->getAttribute('tenant_id') === $second->getAttribute('tenant_id');
    }

    public function roleOwner(User $actor): ?int
    {
        return $this->isMultiTenant() ? $actor->tenant_id : null;
    }

    /**
     * Many tenants exist only where the Subscription module manages them.
     */
    public function isMultiTenant(): bool
    {
        return $this->registry->has('subscription');
    }
}
