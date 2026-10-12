<?php

namespace App\Modules\Team\Models;

use App\Modules\Company\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Permission\Contracts\Role as RoleContract;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * A role owned by one company. Roles without a company are the platform Admin role and the
 * templates new companies are provisioned from.
 */
class Role extends SpatieRole
{
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOwnedBy(Builder $query, int $companyId): void
    {
        $query->where('company_id', $companyId);
    }

    /**
     * The role a company uses under a name: its own role, or the shared one when it has none.
     */
    public static function resolveFor(int $companyId, string $name): ?self
    {
        return static::query()
            ->where('name', $name)
            ->where(fn (Builder $query) => $query->where('company_id', $companyId)->orWhereNull('company_id'))
            ->orderByRaw('company_id is null')
            ->first();
    }

    /**
     * Name lookups prefer the signed-in user's company role over a shared role of the same name.
     *
     * @param  array<string, mixed>  $params
     */
    protected static function findByParam(array $params = []): ?RoleContract
    {
        $companyId = (int) auth()->user()?->company_id;

        return static::query()
            ->where($params)
            ->where(fn (Builder $query) => $query->whereNull('company_id')->when($companyId, fn (Builder $query) => $query->orWhere('company_id', $companyId)))
            ->orderByRaw('company_id is null')
            ->first();
    }
}
