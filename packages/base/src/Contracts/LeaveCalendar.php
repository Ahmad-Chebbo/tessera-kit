<?php

namespace Tessera\Core\Contracts;

/**
 * Who is away on approved leave. Leave answers from its approved requests; without it nobody is ever away, so
 * attendance counts a missed shift as an absence and payroll deducts nothing for leave.
 */
interface LeaveCalendar
{
    public function available(): bool;

    /**
     * The working days people are away between two dates, inclusive, keyed by "userId|YYYY-MM-DD".
     *
     * @param  list<int>|null  $userIds  Only these people, or everyone in the tenant when null.
     * @return array<string, array{label: string, paid: bool}>
     */
    public function daysOff(int $tenantId, string $from, string $to, ?array $userIds = null): array;
}
