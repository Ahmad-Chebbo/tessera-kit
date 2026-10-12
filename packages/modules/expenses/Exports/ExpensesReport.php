<?php

namespace App\Modules\Expenses\Exports;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class ExpensesReport extends ReportDefinition
{
    public function key(): string
    {
        return 'expenses';
    }

    public function permission(): string
    {
        return 'view expenses';
    }

    public function filterRules(int $companyId): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'employee_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)],
            'type' => ['nullable', Rule::in(ExpenseType::values())],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('date', Column::DATE, 14),
            new Column('description', Column::TEXT, 34),
            new Column('category', Column::TEXT, 22),
            new Column('type', Column::TEXT, 14),
            new Column('branch', Column::TEXT, 18),
            new Column('employee', Column::TEXT, 20),
            new Column('reference', Column::TEXT, 18),
            new Column('amount', Column::MONEY, 16),
            new Column('counts_toward_profit', Column::TEXT, 14),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $query = $this->query($context)->with(['category:id,name,type,affects_profit', 'branch:id,name', 'employee:id,name']);

        foreach ($query->lazyByIdDesc((int) config('exports.chunk_size')) as $expense) {
            yield [
                'date' => $expense->expense_date->toDateString(),
                'description' => $expense->description,
                'category' => $expense->category?->name,
                'type' => $context->t('values.'.($expense->category?->type->value ?? 'other')),
                'branch' => $expense->branch?->name,
                'employee' => $expense->employee?->name,
                'reference' => $expense->reference,
                'amount' => (float) $expense->amount,
                'counts_toward_profit' => $context->t($expense->category?->affects_profit ? 'values.yes' : 'values.no'),
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return $this->query($context)->count();
    }

    public function summary(ExportContext $context): array
    {
        $counted = (clone $this->query($context))->whereHas('category', fn (Builder $category) => $category->where('affects_profit', true));

        return [
            $this->figure($context, 'total', (float) $this->query($context)->sum('amount')),
            $this->figure($context, 'counts_toward_profit', (float) $counted->sum('amount')),
            $this->figure($context, 'entries', $this->query($context)->count(), Column::INTEGER),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        $lines = array_filter([$this->periodLine($context), $this->branchLine($context)]);

        if ($categoryId = $context->filter('category_id')) {
            $lines[] = $context->t('filters.category', ['value' => ExpenseCategory::query()->where('company_id', $context->company->id)->whereKey($categoryId)->value('name')]);
        }

        if ($type = $context->filter('type')) {
            $lines[] = $context->t('filters.type', ['value' => $context->t("values.{$type}")]);
        }

        if ($search = $context->filter('search')) {
            $lines[] = $context->t('filters.search', ['value' => $search]);
        }

        return array_values($lines);
    }

    /**
     * @return Builder<Expense>
     */
    private function query(ExportContext $context): Builder
    {
        $query = Expense::query()->where('company_id', $context->company->id);

        foreach (['category_id' => 'expense_category_id', 'employee_id' => 'employee_id'] as $key => $column) {
            if ($value = $context->filter($key)) {
                $query->where($column, $value);
            }
        }

        if ($branchId = $this->branchId($context)) {
            $query->where('branch_id', $branchId);
        }

        if ($type = $context->filter('type')) {
            $query->whereHas('category', fn (Builder $category) => $category->where('type', $type));
        }

        if ($search = $context->filter('search')) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $search).'%';
            $query->where(fn (Builder $inner) => $inner->where('description', 'like', $like)->orWhere('reference', 'like', $like));
        }

        if ($from = $context->date('date_from')) {
            $query->where('expense_date', '>=', $from->toDateString());
        }

        if ($to = $context->date('date_to')) {
            $query->where('expense_date', '<=', $to->toDateString());
        }

        return $query;
    }
}
