<?php

namespace App\Modules\Shifts;

use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Shifts module.
 *
 * The roster of templates, assignments and cycles. POS shifts (the cash drawer) belong to Sales.
 */
class ShiftsServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'shifts',
            name: 'Shifts',
            requires: ['tenant', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('shifts', 'Shifts & cash drawer', ['view shifts', 'manage shifts'], order: 80),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'shifts' => ['view shifts', 'manage shifts'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view shifts', 'manage shifts'],
            'Manager' => ['view shifts', 'manage shifts'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('team', 'shifts.rosterNav', 'shifts.index', 'calendar', 'view shifts', ['shifts.*'], 30),
        ];
    }
}
