<?php

namespace App\Modules\Sales;

use App\Modules\Customers\Models\Customer;
use App\Modules\Sales\Database\Seeders\DemoStoreSeeder;
use App\Modules\Sales\Exports\OrdersReport;
use App\Modules\Sales\Exports\PosShiftClosingReport;
use App\Modules\Sales\Exports\PosShiftsReport;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Services\OrderCustomerActivity;
use App\Modules\Sales\Services\OrderRevenueSource;
use App\Modules\Sales\Services\PosShiftService;
use App\Modules\Team\Models\User;
use Illuminate\Http\Request;
use Tessera\Core\Contracts\CustomerActivity;
use Tessera\Core\Contracts\RevenueSource;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Orders, the till, POS shifts and the cash drawer. Discounts come through a contract.
 */
class SalesServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'sales',
            name: 'Sales',
            requires: ['catalog', 'tenant', 'customers', 'inventory', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('pos', 'Point of sale & orders', ['access pos', 'apply discounts', 'void orders', 'view orders', 'view all orders', 'export orders'], order: 10),
            new PlanFeature('shifts', 'Shifts & cash drawer', ['view shift reports', 'open pos shift'], order: 80),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'pos' => ['access pos', 'apply discounts', 'void orders', 'open pos shift'],
            'sales' => ['view orders', 'view all orders', 'export orders'],
            'shifts' => ['view shift reports'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['access pos', 'apply discounts', 'void orders', 'view orders', 'view all orders', 'export orders', 'open pos shift', 'view shift reports'],
            'Manager' => ['access pos', 'apply discounts', 'void orders', 'view orders', 'view all orders', 'export orders', 'open pos shift', 'view shift reports'],
            'Cashier' => ['access pos', 'open pos shift'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('main', 'nav.pos', 'pos.index', 'terminal', 'access pos', ['pos.*'], 20),
            new MenuItem('team', 'shifts.posShiftsNav', 'pos-shifts.index', 'drawer', 'open pos shift', ['pos-shifts.*'], 40),
            new MenuItem('sales', 'nav.orders', 'orders.index', 'receipt', 'view orders', ['orders.*'], 10),
        ];
    }

    protected function bindings(): void
    {
        $this->app->bind(CustomerActivity::class, OrderCustomerActivity::class);
        $this->app->bind(RevenueSource::class, OrderRevenueSource::class);
    }

    public function boot(): void
    {
        parent::boot();

        Customer::resolveRelationUsing('orders', fn (Customer $customer) => $customer->hasMany(Order::class));
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
            OrdersReport::class,
            PosShiftsReport::class,
            PosShiftClosingReport::class,
        ];
    }

    public function seeders(): array
    {
        return [DemoStoreSeeder::class];
    }
}
