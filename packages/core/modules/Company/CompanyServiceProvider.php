<?php

namespace App\Modules\Company;

use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Companies, branches, locations, exchange rates and settings. A core module that works with Team and Subscription.
 */
class CompanyServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'company',
            name: 'Company',
            requires: [],
            core: true,
        );
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom($this->modulePath('currencies.php'), 'currencies');
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->publishes([$this->modulePath('currencies.php') => config_path('currencies.php')], ['tessera-config', 'tessera-modules-config', 'tessera-company-config']);
        }
    }

    public function features(): array
    {
        return [
            new PlanFeature('branches', 'Branches & locations', ['manage branches', 'manage locations'], order: 70),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'settings' => ['manage settings', 'manage branches', 'manage locations', 'view all branches'],
        ];
    }

    public function extraPermissions(): array
    {
        return ['manage companies'];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Admin' => ['manage companies'],
            'Owner' => ['manage branches', 'manage locations', 'manage settings', 'view all branches'],
            'Manager' => ['manage branches', 'manage locations', 'view all branches'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('admin', 'nav.companies', 'admin.companies.index', 'company', 'manage companies', ['admin.companies.*'], 10),
            new MenuItem('settings', 'settings.title', 'settings.index', 'cog', 'manage settings', ['settings.*'], 20),
            new MenuItem('settings', 'settings.exchangeRates', 'exchange-rates.index', 'currency', 'manage settings', ['exchange-rates.*'], 30),
            new MenuItem('settings', 'nav.branches', 'branches.index', 'branch', 'manage branches', ['branches.*'], 40),
            new MenuItem('settings', 'nav.warehouse', 'locations.index', 'location', 'manage locations', ['locations.*'], 50),
        ];
    }
}
