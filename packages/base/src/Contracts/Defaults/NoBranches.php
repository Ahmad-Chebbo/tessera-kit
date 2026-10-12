<?php

namespace Tessera\Core\Contracts\Defaults;

use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Tessera\Core\Contracts\BranchScope;

class NoBranches implements BranchScope
{
    public function available(): bool
    {
        return false;
    }

    public function canSeeAll(User $user): bool
    {
        return true;
    }

    public function effective(User $user, mixed $requested = null): ?int
    {
        return null;
    }

    public function scope(Builder $query, User $user, mixed $requested = null, string $column = 'branch_id'): Builder
    {
        return $query;
    }

    public function scopeThroughUser(Builder $query, User $user, mixed $requested = null, string $relation = 'user'): Builder
    {
        return $query;
    }

    public function branches(User $user): Collection
    {
        return new Collection;
    }

    public function ensure(User $user, ?int $branchId): void {}
}
