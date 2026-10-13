<?php

namespace App\Modules\Leave;

use App\Modules\Leave\Services\ApprovedLeaveCalendar;
use Tessera\Core\Contracts\LeaveCalendar;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Leave module.
 *
 * Leave types with a yearly allowance, requests that a manager approves, public holidays and who is away. With the
 * Organization module a request goes to the employee's manager; attendance does not count approved leave as absence.
 */
class LeaveServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'leave',
            name: 'Leave',
            requires: ['tenant', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('leave', 'Leave', ['request leave', 'approve leave', 'manage leave'], order: 95),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'leave' => ['request leave', 'approve leave', 'manage leave'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['request leave', 'approve leave', 'manage leave'],
            'Manager' => ['request leave', 'approve leave'],
            'Cashier' => ['request leave'],
            'Stock Keeper' => ['request leave'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('team', 'leave.nav', 'leave.index', 'calendar', 'request leave', ['leave.*'], 55),
        ];
    }

    protected function bindings(): void
    {
        $this->app->bind(LeaveCalendar::class, ApprovedLeaveCalendar::class);
    }
}
