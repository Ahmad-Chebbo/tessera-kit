<?php

namespace App\Modules\Expenses\Actions;

use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Services\ExpenseBranch;
use App\Modules\Team\Models\User;
use Illuminate\Http\UploadedFile;

class SaveExpenseAction
{
    public function __construct(private readonly ExpenseBranch $branches) {}

    /**
     * Records an expense for the actor's tenant, optionally with a receipt.
     *
     * @param  array<string, mixed>  $data  Validated expense fields without the receipt.
     */
    public function create(User $actor, array $data, ?UploadedFile $receipt = null): Expense
    {
        $expense = Expense::create([
            ...$data,
            'branch_id' => $this->branches->forWrite($actor, $data['branch_id'] ?? null),
            'tenant_id' => $actor->tenant_id,
            'created_by' => $actor->id,
        ]);

        $this->saveReceipt($expense, $receipt, false);

        return $expense;
    }

    /**
     * @param  array<string, mixed>  $data  Validated expense fields without the receipt.
     */
    public function update(User $actor, Expense $expense, array $data, ?UploadedFile $receipt = null, bool $removeReceipt = false): Expense
    {
        $expense->update([
            ...$data,
            'spread_months' => $data['spread_months'] ?? null,
            'branch_id' => $this->branches->forWrite($actor, $data['branch_id'] ?? null),
            'employee_id' => $data['employee_id'] ?? null,
            'purchase_id' => $data['purchase_id'] ?? null,
        ]);

        $this->saveReceipt($expense, $receipt, $removeReceipt);

        return $expense;
    }

    private function saveReceipt(Expense $expense, ?UploadedFile $receipt, bool $remove): void
    {
        if ($receipt !== null) {
            $expense->addMedia($receipt)->toMediaCollection('receipt');
        } elseif ($remove) {
            $expense->clearMediaCollection('receipt');
        }
    }
}
