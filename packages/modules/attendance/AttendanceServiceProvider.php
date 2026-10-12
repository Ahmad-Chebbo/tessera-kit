<?php

namespace App\Modules\Attendance;

use App\Modules\Attendance\Exports\AttendanceHistoryReport;
use App\Modules\Attendance\Exports\AttendanceReport;
use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Team\Models\User;
use Illuminate\Http\Request;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Attendance module.
 */
class AttendanceServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'attendance',
            name: 'Attendance',
            requires: ['shifts'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('attendance', 'Attendance', ['view attendance', 'manage attendance', 'record attendance'], order: 90),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'attendance' => ['view attendance', 'manage attendance', 'record attendance'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view attendance', 'manage attendance', 'record attendance'],
            'Manager' => ['view attendance', 'manage attendance', 'record attendance'],
            'Cashier' => ['record attendance'],
            'Stock Keeper' => ['record attendance'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('team', 'attendance.historyNav', 'attendance.index', 'clock', 'record attendance', ['attendance.*'], 50),
        ];
    }

    public function sharedProps(): array
    {
        return [
            'attendance' => fn (Request $request, ?User $user) => $user && $user->company_id
                ? app(AttendanceService::class)->current($user)?->only(['id', 'check_in_at'])
                : null,
        ];
    }

    public function exports(): array
    {
        return [
            AttendanceReport::class,
            AttendanceHistoryReport::class,
        ];
    }
}
