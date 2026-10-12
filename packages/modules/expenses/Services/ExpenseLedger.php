<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Company\Models\Branch;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Works out how much expense falls inside a period. An expense counts on its date, unless it is spread over several
 * months (depreciation), in which case it counts evenly per day across that time, so any period gets its fair share
 * and the shares always add up to the full amount.
 */
class ExpenseLedger
{
    /**
     * @param  array<string, mixed>  $filters  category_id, branch_id, type, employee_id, search
     * @return array{total: float, included: float, count: int, by_category: list<array<string, mixed>>, by_month: list<array<string, mixed>>, by_branch: list<array<string, mixed>>}
     */
    public function breakdown(int $companyId, CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $categories = ExpenseCategory::query()->where('company_id', $companyId)->get()->keyBy('id');
        $byCategory = [];
        $byMonth = [];
        $byBranch = [];

        foreach ($this->slices($companyId, $from, $to, $filters) as [$month, $categoryId, $branchId, $amount]) {
            $category = $categories[$categoryId] ?? null;
            $counts = (bool) ($category?->affects_profit);

            $byCategory[$categoryId] = ($byCategory[$categoryId] ?? 0) + $amount;
            $byBranch[$branchId ?? 0] = ($byBranch[$branchId ?? 0] ?? 0) + $amount;
            $byMonth[$month]['total'] = ($byMonth[$month]['total'] ?? 0) + $amount;
            $byMonth[$month]['included'] = ($byMonth[$month]['included'] ?? 0) + ($counts ? $amount : 0);
        }

        ksort($byMonth);
        $branchNames = Branch::query()->where('company_id', $companyId)->pluck('name', 'id');

        $categoryRows = collect($byCategory)
            ->map(fn (float $total, int $id): array => [
                'category_id' => $id,
                'name' => $categories[$id]->name ?? '',
                'type' => $categories[$id]?->type->value ?? 'other',
                'affects_profit' => (bool) ($categories[$id]->affects_profit ?? false),
                'total' => round($total, 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'total' => round(array_sum($byCategory), 2),
            'included' => round(collect($categoryRows)->where('affects_profit', true)->sum('total'), 2),
            'count' => $this->countExpenses($companyId, $from, $to, $filters),
            'by_category' => $categoryRows,
            'by_month' => collect($byMonth)->map(fn (array $row, string $month): array => ['month' => $month, 'total' => round($row['total'], 2), 'included' => round($row['included'], 2)])->values()->all(),
            'by_branch' => collect($byBranch)
                ->map(fn (float $total, int $id): array => ['branch_id' => $id ?: null, 'name' => $id ? ($branchNames[$id] ?? '') : null, 'total' => round($total, 2)])
                ->sortByDesc('total')
                ->values()
                ->all(),
        ];
    }

    /**
     * Expense that counts toward profit per month, for the profit statement.
     *
     * @return array<string, float> keyed by "YYYY-MM"
     */
    public function includedByMonth(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $rows = $this->breakdown($companyId, $from, $to, array_filter(['branch_id' => $branchId]))['by_month'];

        return collect($rows)->mapWithKeys(fn (array $row): array => [$row['month'] => $row['included']])->all();
    }

    /**
     * Every piece of expense inside the period as [month, category id, branch id, amount].
     *
     * @param  array<string, mixed>  $filters
     * @return iterable<array{0: string, 1: int, 2: int|null, 3: float}>
     */
    private function slices(int $companyId, CarbonImmutable $from, CarbonImmutable $to, array $filters): iterable
    {
        $plain = $this->base($companyId, $filters)
            ->where(fn (Builder $query) => $query->whereNull('expenses.spread_months')->orWhere('expenses.spread_months', '<=', 1))
            ->whereBetween('expenses.expense_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('expenses.expense_date as day, expenses.expense_category_id as category_id, expenses.branch_id as branch_id, SUM(expenses.amount) as amount')
            ->groupBy('expenses.expense_date', 'expenses.expense_category_id', 'expenses.branch_id')
            ->toBase()
            ->cursor();

        foreach ($plain as $row) {
            yield [substr((string) $row->day, 0, 7), (int) $row->category_id, $row->branch_id ? (int) $row->branch_id : null, (float) $row->amount];
        }

        $spread = $this->base($companyId, $filters)
            ->where('expenses.spread_months', '>', 1)
            ->where('expenses.expense_date', '<=', $to->toDateString())
            ->select('expenses.*')
            ->get()
            ->filter(fn (Expense $expense): bool => $expense->lastDay()->greaterThanOrEqualTo($from));

        foreach ($spread as $expense) {
            $start = CarbonImmutable::parse($expense->expense_date->toDateString());
            $end = $expense->lastDay();
            $days = $start->diffInDays($end) + 1;
            $daily = (float) $expense->amount / $days;

            $firstDay = $start->max($from);
            $lastDay = $end->min($to);

            for ($day = $firstDay; $day->lessThanOrEqualTo($lastDay); $day = $day->addDay()) {
                yield [$day->format('Y-m'), $expense->expense_category_id, $expense->branch_id, $daily];
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function countExpenses(int $companyId, CarbonImmutable $from, CarbonImmutable $to, array $filters): int
    {
        return $this->base($companyId, $filters)
            ->where('expenses.expense_date', '<=', $to->toDateString())
            ->get(['expenses.id', 'expenses.expense_date', 'expenses.spread_months'])
            ->filter(function (Expense $expense) use ($from, $to): bool {
                return $expense->isSpread()
                    ? $expense->lastDay()->greaterThanOrEqualTo($from)
                    : $expense->expense_date->greaterThanOrEqualTo($from) && $expense->expense_date->lessThanOrEqualTo($to);
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Expense>
     */
    private function base(int $companyId, array $filters): Builder
    {
        $query = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->where('expenses.company_id', $companyId);

        foreach (['category_id' => 'expenses.expense_category_id', 'branch_id' => 'expenses.branch_id', 'employee_id' => 'expenses.employee_id'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['type'])) {
            $query->where('expense_categories.type', $filters['type']);
        }

        if (! empty($filters['search'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $filters['search']).'%';
            $query->where(fn (Builder $inner) => $inner->where('expenses.description', 'like', $like)->orWhere('expenses.reference', 'like', $like));
        }

        return $query;
    }

    /**
     * Names of the months in a range, so charts can show empty months too.
     *
     * @return Collection<int, string>
     */
    public function monthsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $months = collect();

        for ($month = $from->startOfMonth(); $month->lessThanOrEqualTo($to); $month = $month->addMonthNoOverflow()) {
            $months->push($month->format('Y-m'));
        }

        return $months;
    }
}
