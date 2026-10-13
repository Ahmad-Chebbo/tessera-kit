<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\LeaveCalendar;

class NoLeaveCalendar implements LeaveCalendar
{
    public function available(): bool
    {
        return false;
    }

    public function daysOff(int $tenantId, string $from, string $to, ?array $userIds = null): array
    {
        return [];
    }
}
