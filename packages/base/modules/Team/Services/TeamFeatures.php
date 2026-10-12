<?php

namespace App\Modules\Team\Services;

use Tessera\Core\Contracts\BranchScope;
use Tessera\Engine\ModuleRegistry;

/**
 * Which of the optional parts of Team are on. A project with only users and roles gets none of them; one with the
 * Tenant module gets all, unless the team config says otherwise.
 */
class TeamFeatures
{
    public function __construct(private readonly ModuleRegistry $registry, private readonly BranchScope $branches) {}

    public function employeeProfiles(): bool
    {
        return $this->configured('team.employee_profiles');
    }

    public function activityLog(): bool
    {
        return $this->configured('team.activity_log');
    }

    public function branches(): bool
    {
        return $this->branches->available();
    }

    /**
     * @return array{branches: bool, employeeProfiles: bool, activityLog: bool}
     */
    public function toArray(): array
    {
        return ['branches' => $this->branches(), 'employeeProfiles' => $this->employeeProfiles(), 'activityLog' => $this->activityLog()];
    }

    private function configured(string $key): bool
    {
        return config($key) === null ? $this->registry->has('tenant') : (bool) config($key);
    }
}
