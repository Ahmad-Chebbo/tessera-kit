<?php

namespace App\Modules\Company\Services;

use App\Modules\Company\Models\Branch;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Tessera\Core\Contracts\BranchScope;

/**
 * Decides which branches a user may look at. People with the "view all branches" permission can see every branch of
 * their company and narrow the view to one. Everyone else is held to their own branch, whatever the request says.
 * Controllers ask this class instead of repeating the rule, so every page behaves the same.
 */
class BranchAccess implements BranchScope
{
    /**
     * A branch id no real branch has, used to match nothing for people who belong to no branch.
     */
    public const NONE = -1;

    public function available(): bool
    {
        return true;
    }

    public function canSeeAll(User $user): bool
    {
        return $user->company_id !== null && ($user->isSuperAdmin() || $user->can('view all branches'));
    }

    /**
     * The branches the user may choose from: all of the company's, or just their own.
     *
     * @return Collection<int, Branch>
     */
    public function branches(User $user): Collection
    {
        return Branch::query()
            ->where('company_id', $user->company_id)
            ->when(! $this->canSeeAll($user), fn (Builder $query) => $query->where('id', $user->branch_id ?? self::NONE))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * The single branch a page should be limited to, or null for every branch. A restricted user always gets their
     * own branch; someone who can see everything gets the branch they asked for, if it is one of the company's.
     */
    public function effective(User $user, mixed $requested = null): ?int
    {
        if (! $this->canSeeAll($user)) {
            return $user->branch_id ?? self::NONE;
        }

        if (! is_numeric($requested) || (int) $requested <= 0) {
            return null;
        }

        return Branch::query()->where('company_id', $user->company_id)->whereKey((int) $requested)->exists() ? (int) $requested : null;
    }

    /**
     * Limits a query on a table that has a branch column.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scope(Builder $query, User $user, mixed $requested = null, string $column = 'branch_id'): Builder
    {
        $branchId = $this->effective($user, $requested);

        return $branchId === null ? $query : $query->where($column, $branchId);
    }

    /**
     * Limits a query on records that belong to a branch through the employee they belong to.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeThroughUser(Builder $query, User $user, mixed $requested = null, string $relation = 'user'): Builder
    {
        $branchId = $this->effective($user, $requested);

        return $branchId === null ? $query : $query->whereHas($relation, fn (Builder $inner) => $inner->where('branch_id', $branchId));
    }

    /**
     * Whether the user may work with a record that belongs to the given branch.
     */
    public function allows(User $user, ?int $branchId): bool
    {
        if ($this->canSeeAll($user)) {
            return true;
        }

        return $branchId !== null && $branchId === $user->branch_id;
    }

    public function ensure(User $user, ?int $branchId): void
    {
        abort_unless($this->allows($user, $branchId), 403);
    }
}
