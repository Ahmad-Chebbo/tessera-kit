<?php

namespace Tessera\Core\Contracts;

use Carbon\CarbonImmutable;

/**
 * The net profit statement: sales less discounts, cost of goods and the expenses that count toward profit.
 * The Expenses module answers; without it there is no statement.
 */
interface NetProfit
{
    public function available(): bool;

    /**
     * @return array{months: list<array<string, mixed>>, totals: array<string, float>}
     */
    public function forPeriod(int $tenantId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array;
}
