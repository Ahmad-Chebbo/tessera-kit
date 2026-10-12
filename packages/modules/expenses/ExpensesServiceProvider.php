<?php

namespace App\Modules\Expenses;

use App\Modules\Expenses\Exports\ExpensesBreakdownReport;
use App\Modules\Expenses\Exports\ExpensesReport;
use App\Modules\Expenses\Services\ProfitStatement;
use App\Modules\Expenses\Services\RecurringExpenseRunner;
use Illuminate\Console\Scheduling\Schedule;
use Tessera\Core\Contracts\NetProfit;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Expenses module.
 *
 * Sales figures and purchases come through contracts, so the module works without Sales and Inventory.
 */
class ExpensesServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'expenses',
            name: 'Expenses',
            requires: [],
            packages: ['spatie/laravel-medialibrary'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('expenses', 'Expenses', ['view expenses', 'create expenses', 'edit expenses', 'delete expenses'], order: 50),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'expenses' => ['view expenses', 'create expenses', 'edit expenses', 'delete expenses'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view expenses', 'create expenses', 'edit expenses', 'delete expenses'],
            'Manager' => ['view expenses', 'create expenses'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('finance', 'expenses.nav', 'expenses.index', 'wallet', 'view expenses', ['expenses.*', 'expense-categories.*', 'recurring-expenses.*'], 10),
        ];
    }

    public function schedule(Schedule $schedule): void
    {
        $schedule->call(fn () => app(RecurringExpenseRunner::class)->run())->dailyAt('00:30')->name('run-recurring-expenses')->onOneServer();
    }

    protected function bindings(): void
    {
        $this->app->bind(NetProfit::class, ProfitStatement::class);
    }

    public function exports(): array
    {
        return [
            ExpensesReport::class,
            ExpensesBreakdownReport::class,
        ];
    }
}
