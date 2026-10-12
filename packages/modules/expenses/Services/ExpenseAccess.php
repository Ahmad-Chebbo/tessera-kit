<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Company\Services\BranchAccess;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Team\Models\User;

class ExpenseAccess
{
    public function __construct(private readonly BranchAccess $branches) {}

    /**
     * Aborts with 403 unless the expense belongs to the user's company and to a branch they may work with.
     */
    public function ensure(User $user, Expense $expense): void
    {
        abort_unless($expense->company_id === $user->company_id, 403);
        $this->branches->ensure($user, $expense->branch_id);
    }
}
