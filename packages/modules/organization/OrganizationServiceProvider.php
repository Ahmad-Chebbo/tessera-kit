<?php

namespace App\Modules\Organization;

use App\Modules\Organization\Services\AssignedReportingLines;
use Tessera\Core\Contracts\ReportingLines;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Organization module.
 *
 * Departments, job positions and who reports to whom, with an organization chart. Other modules ask it for a person's
 * manager through the ReportingLines contract.
 */
class OrganizationServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'organization',
            name: 'Organization',
            requires: ['tenant', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('organization', 'Organization chart', ['view organization', 'manage organization'], order: 70),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'organization' => ['view organization', 'manage organization'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view organization', 'manage organization'],
            'Manager' => ['view organization'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('team', 'organization.nav', 'organization.index', 'org-chart', 'view organization', ['organization.*'], 25),
        ];
    }

    protected function bindings(): void
    {
        $this->app->bind(ReportingLines::class, AssignedReportingLines::class);
    }
}
