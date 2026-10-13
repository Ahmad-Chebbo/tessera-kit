<?php

namespace App\Modules\Organization\Services;

use App\Modules\Organization\Models\Department;
use App\Modules\Organization\Models\EmployeeAssignment;

/**
 * Answers the "would this loop" questions that keep departments and reporting lines a tree.
 */
class OrganizationTree
{
    /**
     * Whether making $parentId the parent of the department would put the department under itself.
     */
    public function departmentLoops(int $departmentId, ?int $parentId): bool
    {
        $seen = [];

        while ($parentId !== null && ! in_array($parentId, $seen, true)) {
            if ($parentId === $departmentId) {
                return true;
            }

            $seen[] = $parentId;
            $parentId = Department::query()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }

    /**
     * Whether making $managerId the manager of the user would make the user their own boss through the chain.
     */
    public function reportingLoops(int $userId, ?int $managerId): bool
    {
        $seen = [];

        while ($managerId !== null && ! in_array($managerId, $seen, true)) {
            if ($managerId === $userId) {
                return true;
            }

            $seen[] = $managerId;
            $managerId = EmployeeAssignment::query()->where('user_id', $managerId)->value('manager_id');
        }

        return false;
    }
}
