<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Expenses\Models\Expense;
use Illuminate\Database\Eloquent\Builder;

/**
 * The expenses of a tenant inside a period, narrowed by the filters both the pages and the API offer.
 */
class ExpenseQuery
{
    /**
     * @param  array<string, mixed>  $filters  category_id, branch_id, employee_id, type and search.
     * @return Builder<Expense>
     */
    public function filtered(int $tenantId, string $from, string $to, array $filters = []): Builder
    {
        $query = Expense::query()->where('tenant_id', $tenantId)->whereBetween('expense_date', [$from, $to]);

        foreach (['category_id' => 'expense_category_id', 'branch_id' => 'branch_id', 'employee_id' => 'employee_id'] as $key => $column) {
            if (isset($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (isset($filters['type'])) {
            $query->whereHas('category', fn (Builder $category) => $category->where('type', $filters['type']));
        }

        if (isset($filters['search'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']).'%';
            $query->where(fn (Builder $inner) => $inner->where('description', 'like', $like)->orWhere('reference', 'like', $like));
        }

        return $query;
    }

    /**
     * @param  Builder<Expense>  $query
     * @return array{total: float, counted: float, count: int}
     */
    public function summary(Builder $query): array
    {
        return [
            'total' => round((float) (clone $query)->sum('amount'), 2),
            'counted' => round((float) (clone $query)->whereHas('category', fn (Builder $category) => $category->where('affects_profit', true))->sum('amount'), 2),
            'count' => (clone $query)->count(),
        ];
    }
}
