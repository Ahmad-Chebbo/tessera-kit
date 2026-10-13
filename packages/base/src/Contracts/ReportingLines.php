<?php

namespace Tessera\Core\Contracts;

/**
 * Who reports to whom. Organization answers from its employee assignments; without it nobody has a manager, so a module
 * that asks for approvals falls back to people who hold the permission.
 */
interface ReportingLines
{
    public function available(): bool;

    public function managerOf(int $userId): ?int;

    /**
     * The users who report directly to the manager.
     *
     * @return list<int>
     */
    public function reportsOf(int $managerId): array;
}
