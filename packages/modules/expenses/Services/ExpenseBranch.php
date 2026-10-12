<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Company\Services\BranchAccess;
use App\Modules\Team\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The branch an expense is booked to: the one chosen by people who see every branch, always their own for everyone else.
 */
class ExpenseBranch
{
    public function __construct(private readonly BranchAccess $access) {}

    /**
     * @throws AuthorizationException when someone who is held to a branch belongs to none.
     */
    public function forWrite(User $actor, mixed $requested): ?int
    {
        if ($this->access->canSeeAll($actor)) {
            return $requested === null || $requested === '' ? null : (int) $requested;
        }

        if ($actor->branch_id === null) {
            throw new AuthorizationException('You are not assigned to a branch.');
        }

        return $actor->branch_id;
    }
}
