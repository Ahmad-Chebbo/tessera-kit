<?php

namespace Tessera\Core\Contracts\Defaults;

use Carbon\CarbonImmutable;
use Tessera\Core\Contracts\RevenueSource;

class NoRevenueSource implements RevenueSource
{
    public function byMonth(int $companyId, CarbonImmutable $from, CarbonImmutable $to, ?int $branchId = null): array
    {
        return [];
    }
}
