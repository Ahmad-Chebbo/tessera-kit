<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\EmployeeAssignment;
use Tessera\Core\Contracts\ReportingLines;

class AssignedReportingLines implements ReportingLines
{
    public function available(): bool
    {
        return true;
    }

    public function managerOf(int $userId): ?int
    {
        return EmployeeAssignment::query()->where('user_id', $userId)->value('manager_id');
    }

    public function reportsOf(int $managerId): array
    {
        return EmployeeAssignment::query()->where('manager_id', $managerId)->pluck('user_id')->map(fn (mixed $id): int => (int) $id)->all();
    }
}
