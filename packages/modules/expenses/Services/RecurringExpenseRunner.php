<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\RecurringExpense;
use Carbon\CarbonImmutable;

/**
 * Turns recurring expenses into real expenses when they fall due. Safe to run any number of times a day, and it
 * catches up on days it missed.
 */
class RecurringExpenseRunner
{
    private const MAX_CATCH_UP = 36;

    public function run(?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $created = 0;

        RecurringExpense::query()
            ->where('is_active', true)
            ->where('next_run_on', '<=', $today->toDateString())
            ->orderBy('id')
            ->each(function (RecurringExpense $recurring) use ($today, &$created): void {
                $created += $this->runOne($recurring, $today);
            });

        return $created;
    }

    public function runOne(RecurringExpense $recurring, CarbonImmutable $today): int
    {
        $created = 0;
        $start = CarbonImmutable::parse($recurring->starts_on->toDateString());
        $end = $recurring->ends_on ? CarbonImmutable::parse($recurring->ends_on->toDateString()) : null;

        for ($guard = 0; $guard < self::MAX_CATCH_UP; $guard++) {
            $due = $recurring->frequency->dateOfRun($start, $recurring->runs_count);

            if ($due->greaterThan($today) || ($end && $due->greaterThan($end))) {
                break;
            }

            $expense = Expense::query()->firstOrCreate(
                ['recurring_expense_id' => $recurring->id, 'expense_date' => $due->toDateString()],
                [
                    'tenant_id' => $recurring->tenant_id,
                    'branch_id' => $recurring->branch_id,
                    'expense_category_id' => $recurring->expense_category_id,
                    'created_by' => $recurring->created_by,
                    'employee_id' => $recurring->employee_id,
                    'amount' => $recurring->amount,
                    'description' => $recurring->description,
                    'spread_months' => $recurring->spread_months,
                    'notes' => $recurring->notes,
                ],
            );

            $created += $expense->wasRecentlyCreated ? 1 : 0;
            $recurring->runs_count++;
            $recurring->last_run_on = $due;
        }

        $next = $recurring->frequency->dateOfRun($start, $recurring->runs_count);
        $recurring->next_run_on = $next;
        $recurring->is_active = ! ($end && $next->greaterThan($end));
        $recurring->save();

        return $created;
    }
}
