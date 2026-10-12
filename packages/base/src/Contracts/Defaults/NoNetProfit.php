<?php

namespace Tessera\Core\Contracts\Defaults;

use Carbon\CarbonImmutable;
use Tessera\Core\Contracts\NetProfit;

class NoNetProfit implements NetProfit
{
    public function available(): bool
    {
        return false;
    }

    public function forPeriod(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        return ['months' => [], 'totals' => []];
    }
}
