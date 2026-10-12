<?php

namespace Tessera\Core\Contracts\Defaults;

use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Tessera\Core\Contracts\Tenancy;

class NoTenancy implements Tenancy
{
    public function available(): bool
    {
        return false;
    }

    public function scope(Builder $query, ?int $tenantId, string $column = 'tenant_id'): Builder
    {
        return $query;
    }

    public function scopeRule(Unique $rule, ?int $tenantId, string $column = 'tenant_id'): Unique
    {
        return $rule;
    }

    public function attributes(?int $tenantId): array
    {
        return [];
    }

    public function sameTenant(Model $first, Model $second): bool
    {
        return true;
    }

    public function roleOwner(User $actor): ?int
    {
        return null;
    }
}
