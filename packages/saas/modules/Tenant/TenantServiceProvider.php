<?php

namespace App\Modules\Tenant;

use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\BranchAccess;
use App\Modules\Tenant\Services\CurrencyService;
use Illuminate\Http\Request;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Tenants, branches, locations, exchange rates and settings. A core module that works with Team and Subscription.
 */
class TenantServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'tenant',
            name: 'Tenant',
            requires: [],
            core: true,
        );
    }

    public function register(): void
    {
        parent::register();

        $this->mergeConfigFrom($this->modulePath('currencies.php'), 'currencies');
    }

    protected function bindings(): void
    {
        $this->app->bind(BranchScope::class, BranchAccess::class);
        $this->app->singleton(CurrencyService::class, fn () => new CurrencyService);
    }

    /**
     * Team does not know about tenants; the relations to them are added here.
     */
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('tenant', fn (User $user) => $user->belongsTo(Tenant::class, 'tenant_id'));
        User::resolveRelationUsing('branch', fn (User $user) => $user->belongsTo(Branch::class, 'branch_id'));
        Role::resolveRelationUsing('tenant', fn (Role $role) => $role->belongsTo(Tenant::class, 'tenant_id'));
        EmployeeProfile::resolveRelationUsing('tenant', fn (EmployeeProfile $profile) => $profile->belongsTo(Tenant::class, 'tenant_id'));

        if ($this->app->runningInConsole()) {
            $this->publishes([$this->modulePath('currencies.php') => config_path('currencies.php')], ['tessera-config', 'tessera-modules-config', 'tessera-tenant-config']);
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
        return ['manage tenants'];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Admin' => ['manage tenants'],
            'Owner' => ['manage branches', 'manage locations', 'manage settings', 'view all branches'],
            'Manager' => ['manage branches', 'manage locations', 'view all branches'],
        ];
    }

    public function sharedProps(): array
    {
        return [
            'currency' => fn (Request $request, ?User $user) => $user?->tenant?->currency ?? 'USD',
            'branchScope' => fn (Request $request, ?User $user) => $user && $user->tenant_id ? [
                'canSeeAll' => app(BranchAccess::class)->canSeeAll($user),
                'branches' => app(BranchAccess::class)->branches($user)->map->only(['id', 'name'])->values(),
                'branchId' => $user->branch_id,
            ] : null,
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('admin', 'nav.tenants', 'admin.tenants.index', 'tenant', 'manage tenants', ['admin.tenants.*'], 10),
            new MenuItem('settings', 'settings.title', 'settings.index', 'cog', 'manage settings', ['settings.*'], 20),
            new MenuItem('settings', 'settings.exchangeRates', 'exchange-rates.index', 'currency', 'manage settings', ['exchange-rates.*'], 30),
            new MenuItem('settings', 'nav.branches', 'branches.index', 'branch', 'manage branches', ['branches.*'], 40),
            new MenuItem('settings', 'nav.warehouse', 'locations.index', 'location', 'manage locations', ['locations.*'], 50),
        ];
    }
}
