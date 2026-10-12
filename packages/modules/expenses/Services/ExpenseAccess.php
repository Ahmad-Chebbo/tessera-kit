<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Expenses\Models\Expense;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Services\BranchAccess;

class ExpenseAccess
{
    public function __construct(private readonly BranchAccess $branches) {}

    /**
     * Aborts with 403 unless the expense belongs to the user's tenant and to a branch they may work with.
     */
    public function ensure(User $user, Expense $expense): void
    {
        abort_unless($expense->tenant_id === $user->tenant_id, 403);
        $this->branches->ensure($user, $expense->branch_id);
    }
}
