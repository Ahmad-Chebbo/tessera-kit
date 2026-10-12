<?php

namespace App\Modules\Expenses\Exports;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Services\ExpenseLedger;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

/**
 * The expense report by category, with spread expenses counted evenly across their months like the on-screen report.
 */
class ExpensesBreakdownReport extends ReportDefinition
{
    public function __construct(private readonly ExpenseLedger $ledger) {}

    public function key(): string
    {
        return 'expenses-report';
    }

    public function permission(): string
    {
        return 'view expenses';
    }

    public function filterRules(int $tenantId): array
    {
        return [
            'category_id' => ['nullable', 'integer', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'type' => ['nullable', Rule::in(ExpenseType::values())],
        ];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('category', Column::TEXT, 30),
            new Column('type', Column::TEXT, 16),
            new Column('counts_toward_profit', Column::TEXT, 16),
            new Column('amount', Column::MONEY, 18),
            new Column('share', Column::PERCENT, 12),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $breakdown = $this->breakdown($context);

        foreach ($breakdown['by_category'] as $row) {
            yield [
                'category' => $row['name'],
                'type' => $context->t('values.'.$row['type']),
                'counts_toward_profit' => $context->t($row['affects_profit'] ? 'values.yes' : 'values.no'),
                'amount' => $row['total'],
                'share' => $breakdown['total'] > 0 ? round($row['total'] / $breakdown['total'] * 100, 2) : 0,
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return count($this->breakdown($context)['by_category']);
    }

    public function summary(ExportContext $context): array
    {
        $breakdown = $this->breakdown($context);

        return [
            $this->figure($context, 'total', $breakdown['total']),
            $this->figure($context, 'counts_toward_profit', $breakdown['included']),
            $this->figure($context, 'entries', $breakdown['count'], Column::INTEGER),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        [$from, $to] = $this->range($context);

        return array_values(array_filter([$context->t('filters.period', ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $this->branchLine($context)]));
    }

    public function filename(ExportContext $context): string
    {
        [$from, $to] = $this->range($context);

        return "expenses-report_{$from->toDateString()}_{$to->toDateString()}";
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(ExportContext $context): array
    {
        $today = CarbonImmutable::now($context->timezone())->startOfDay();
        $to = $context->date('date_to') ?? $today;
        $from = $context->date('date_from') ?? $to->subDays(29);

        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }

    /**
     * @return array{total: float, included: float, count: int, by_category: list<array<string, mixed>>}
     */
    private function breakdown(ExportContext $context): array
    {
        return $context->remember('expense-breakdown', function () use ($context): array {
            [$from, $to] = $this->range($context);

            return $this->ledger->breakdown($context->tenant->id, $from, $to, array_filter([...array_intersect_key($context->filters, array_flip(['category_id', 'type'])), 'branch_id' => $this->branchId($context)], fn ($value) => $value !== null));
        });
    }
}
