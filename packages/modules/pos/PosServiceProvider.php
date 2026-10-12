<?php

namespace App\Modules\Pos;

use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Exports\PosShiftClosingReport;
use App\Modules\Pos\Exports\PosShiftsReport;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Pos\Services\PosShiftService;
use App\Modules\Team\Models\User;
use Illuminate\Http\Request;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * The till: a screen for selling at the counter, held orders, sales queued while the connection is down, and the cash
 * drawer shifts. A sale is placed as an order by the Orders module.
 */
class PosServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'pos',
            name: 'Point of sale',
            requires: ['orders', 'catalog', 'tenant', 'customers', 'inventory', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('pos', 'Point of sale', ['access pos', 'apply discounts'], order: 10),
            new PlanFeature('shifts', 'Shifts & cash drawer', ['view shift reports', 'open pos shift'], order: 80),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'pos' => ['access pos', 'apply discounts', 'open pos shift'],
            'shifts' => ['view shift reports'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['access pos', 'apply discounts', 'open pos shift', 'view shift reports'],
            'Manager' => ['access pos', 'apply discounts', 'open pos shift', 'view shift reports'],
            'Cashier' => ['access pos', 'open pos shift'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('main', 'nav.pos', 'pos.index', 'terminal', 'access pos', ['pos.*'], 20),
            new MenuItem('team', 'shifts.posShiftsNav', 'pos-shifts.index', 'drawer', 'open pos shift', ['pos-shifts.*'], 40),
        ];
    }

    public function boot(): void
    {
        parent::boot();

        Order::resolveRelationUsing('posShift', fn (Order $order) => $order->belongsTo(PosShift::class, 'pos_shift_id'));
    }

    public function sharedProps(): array
    {
        return [
            'posShift' => fn (Request $request, ?User $user) => $user && $user->tenant_id
                ? app(PosShiftService::class)->current($user)?->only(['id', 'opened_at'])
                : null,
            'requirePosShift' => fn (Request $request, ?User $user) => (bool) $user?->tenant?->require_pos_shift,
        ];
    }

    public function exports(): array
    {
        return [
            PosShiftsReport::class,
            PosShiftClosingReport::class,
        ];
    }
}
