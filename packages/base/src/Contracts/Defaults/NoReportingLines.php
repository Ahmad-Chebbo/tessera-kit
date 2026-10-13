<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\ReportingLines;

class NoReportingLines implements ReportingLines
{
    public function available(): bool
    {
        return false;
    }

    public function managerOf(int $userId): ?int
    {
        return null;
    }

    public function reportsOf(int $managerId): array
    {
        return [];
    }
}
