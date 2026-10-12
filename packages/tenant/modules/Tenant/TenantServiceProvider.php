<?php

namespace App\Modules\Tenant;

use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\BranchAccess;
use App\Modules\Tenant\Services\CurrencyService;
use App\Modules\Tenant\Services\TenantAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Core\Contracts\Tenancy;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * The tenant, its branches, stock locations, exchange rates and settings. A project with one business has one tenant,
 * made on first use; the Subscription module adds many tenants and plans on top.
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
        $this->app->singleton(TenantAccess::class);
        $this->app->bind(Tenancy::class, TenantAccess::class);
        $this->app->bind(BranchScope::class, BranchAccess::class);
        $this->app->singleton(CurrencyService::class, fn () => new CurrencyService);
    }

    /**
     * Team does not know about tenants; the relations to them are added here. In a project with a single business every
     * new user is put in that business.
     */
    public function boot(): void
    {
        parent::boot();

        User::creating(function (User $user): void {
            if ($user->tenant_id === null && ! $this->app->make(TenantAccess::class)->isMultiTenant() && Schema::hasColumn('users', 'tenant_id')) {
                $tenant = Tenant::default();

                $user->tenant_id = $tenant->id;
                $user->branch_id ??= $tenant->mainBranch()?->id;
            }
        });

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

    public function defaultRolePermissions(): array
    {
        return [
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
            new MenuItem('settings', 'settings.title', 'settings.index', 'cog', 'manage settings', ['settings.*'], 20),
            new MenuItem('settings', 'settings.exchangeRates', 'exchange-rates.index', 'currency', 'manage settings', ['exchange-rates.*'], 30),
            new MenuItem('settings', 'nav.branches', 'branches.index', 'branch', 'manage branches', ['branches.*'], 40),
            new MenuItem('settings', 'nav.warehouse', 'locations.index', 'location', 'manage locations', ['locations.*'], 50),
        ];
    }
}
