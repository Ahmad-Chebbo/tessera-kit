<?php

namespace App\Modules\Expenses\Actions;

use App\Modules\Expenses\Models\RecurringExpense;
use App\Modules\Expenses\Services\ExpenseBranch;
use App\Modules\Expenses\Services\RecurringExpenseRunner;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;

class SaveRecurringExpenseAction
{
    public function __construct(
        private readonly ExpenseBranch $branches,
        private readonly RecurringExpenseRunner $runner,
    ) {}

    /**
     * Creates a recurring expense and records the entries that are already due.
     *
     * @param  array<string, mixed>  $data  Validated fields.
     * @return array{0: RecurringExpense, 1: int} The recurring expense and how many entries were recorded right away.
     */
    public function create(User $actor, array $data, bool $active = true): array
    {
        $recurring = RecurringExpense::create([
            ...$data,
            'branch_id' => $this->branches->forWrite($actor, $data['branch_id'] ?? null),
            'company_id' => $actor->company_id,
            'created_by' => $actor->id,
            'next_run_on' => $data['starts_on'],
            'runs_count' => 0,
            'is_active' => $active,
        ]);

        $created = $recurring->is_active ? $this->runner->runOne($recurring, CarbonImmutable::today()) : 0;

        return [$recurring, $created];
    }

    /**
     * The frequency and start date cannot change once entries have been recorded, so they are left alone.
     *
     * @param  array<string, mixed>  $data  Validated fields.
     */
    public function update(User $actor, RecurringExpense $recurring, array $data, bool $active = true): RecurringExpense
    {
        $recurring->update([
            ...collect($data)->except(['frequency', 'starts_on'])->all(),
            'branch_id' => $this->branches->forWrite($actor, $data['branch_id'] ?? null),
            'employee_id' => $data['employee_id'] ?? null,
            'spread_months' => $data['spread_months'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'is_active' => $active,
        ]);

        return $recurring;
    }
}
