<?php

namespace Tessera\Core\Contracts;

use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Which branches a user may look at. The Company module answers; without it there are no branches, and everyone sees
 * everything.
 */
interface BranchScope
{
    public function available(): bool;

    public function canSeeAll(User $user): bool;

    /**
     * The single branch a page should be limited to, or null for every branch.
     */
    public function effective(User $user, mixed $requested = null): ?int;

    /**
     * Limits a query on a table that has a branch column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query, User $user, mixed $requested = null, string $column = 'branch_id'): Builder;

    /**
     * Limits a query on records that belong to a branch through the user they belong to.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeThroughUser(Builder $query, User $user, mixed $requested = null, string $relation = 'user'): Builder;

    /**
     * The branches the user may choose from, as id and name.
     *
     * @return Collection<int, Model>
     */
    public function branches(User $user): Collection;

    public function ensure(User $user, ?int $branchId): void;
}
