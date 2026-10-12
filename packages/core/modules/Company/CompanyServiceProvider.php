<?php

namespace App\Modules\Company;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Company\Services\CurrencyService;
use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use Illuminate\Http\Request;
use Tessera\Core\Contracts\BranchScope;
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

    protected function bindings(): void
    {
        $this->app->bind(BranchScope::class, BranchAccess::class);
        $this->app->singleton(CurrencyService::class, fn () => new CurrencyService);
    }

    /**
     * Team does not know about companies; the relations to them are added here.
     */
    public function boot(): void
    {
        parent::boot();

        User::resolveRelationUsing('company', fn (User $user) => $user->belongsTo(Company::class, 'company_id'));
        User::resolveRelationUsing('branch', fn (User $user) => $user->belongsTo(Branch::class, 'branch_id'));
        Role::resolveRelationUsing('company', fn (Role $role) => $role->belongsTo(Company::class, 'company_id'));
        EmployeeProfile::resolveRelationUsing('company', fn (EmployeeProfile $profile) => $profile->belongsTo(Company::class, 'company_id'));

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

    public function sharedProps(): array
    {
        return [
            'currency' => fn (Request $request, ?User $user) => $user?->company?->currency ?? 'USD',
            'branchScope' => fn (Request $request, ?User $user) => $user && $user->company_id ? [
                'canSeeAll' => app(BranchAccess::class)->canSeeAll($user),
                'branches' => app(BranchAccess::class)->branches($user)->map->only(['id', 'name'])->values(),
                'branchId' => $user->branch_id,
            ] : null,
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
