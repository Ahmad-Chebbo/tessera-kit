<?php

namespace App\Modules\Expenses\Services;

use Carbon\CarbonImmutable;
use Tessera\Core\Contracts\NetProfit;
use Tessera\Core\Contracts\RevenueSource;

/**
 * Sales, discounts, cost of goods and expenses side by side, month by month, ending in net profit.
 * Revenue is what was rung up on completed orders; discounts are taken off it; only expense categories marked
 * "include in profit" are deducted.
 */
class ProfitStatement implements NetProfit
{
    public function __construct(private readonly ExpenseLedger $ledger, private readonly RevenueSource $revenue) {}

    public function available(): bool
    {
        return true;
    }

    /**
     * @return array{months: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function forPeriod(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        $revenue = $this->revenue->byMonth($companyId, $from, $to, $branchId);

        $months = [];

        foreach ($this->ledger->monthsBetween($from, $to) as $month) {
            $months[$month] = ['month' => $month, 'revenue' => 0.0, 'discounts' => 0.0, 'cogs' => 0.0, 'expenses' => 0.0];
        }

        foreach ($revenue as $month => $figures) {
            if (isset($months[$month])) {
                $months[$month] = [...$months[$month], ...$figures];
            }
        }

        foreach ($this->ledger->includedByMonth($companyId, $from, $to, $branchId) as $month => $amount) {
            $months[$month]['expenses'] += $amount;
        }

        $rows = collect($months)->map(fn (array $row): array => $this->finish($row))->values()->all();

        $totals = $this->finish([
            'revenue' => array_sum(array_column($rows, 'revenue')),
            'discounts' => array_sum(array_column($rows, 'discounts')),
            'cogs' => array_sum(array_column($rows, 'cogs')),
            'expenses' => array_sum(array_column($rows, 'expenses')),
        ]);

        return ['months' => $rows, 'totals' => $totals];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function finish(array $row): array
    {
        $net = $row['revenue'] - $row['discounts'];
        $gross = $net - $row['cogs'];

        return [
            ...$row,
            'revenue' => round($row['revenue'], 2),
            'discounts' => round($row['discounts'], 2),
            'cogs' => round($row['cogs'], 2),
            'expenses' => round($row['expenses'], 2),
            'net_sales' => round($net, 2),
            'gross_profit' => round($gross, 2),
            'net_profit' => round($gross - $row['expenses'], 2),
        ];
    }
}
