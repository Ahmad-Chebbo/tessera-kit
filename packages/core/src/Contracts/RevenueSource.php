<?php

namespace Tessera\Core\Contracts;

use Carbon\CarbonImmutable;

/**
 * Sales figures for profit statements. The Sales module answers; without it every month is empty.
 */
interface RevenueSource
{
    /**
     * Revenue, discounts and cost of goods of completed sales, keyed by month ("Y-m").
     *
     * @return array<string, array{revenue: float, discounts: float, cogs: float}>
     */
    public function byMonth(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array;
}
