<?php

namespace App\Modules\Subscription;

use App\Modules\Subscription\Database\Factories\GivesTenantsAPlan;
use App\Modules\Subscription\Database\Seeders\PlanSeeder;
use App\Modules\Subscription\Http\Middleware\EnsureSubscriptionIsUsable;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\Entitlements;
use App\Modules\Subscription\Services\EntitlementsPlanLimits;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Database\Factories\TenantFactory;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;

/**
 * Plans, subscriptions and limits, and the platform admin's screens for creating and managing tenants.
 */
class SubscriptionServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'subscription',
            name: 'Subscription',
            requires: [],
            core: false,
        );
    }

    public function menu(): array
    {
        return [
            new MenuItem('admin', 'nav.tenants', 'admin.tenants.index', 'tenant', 'manage tenants', ['admin.tenants.*'], 10),
            new MenuItem('admin', 'plans.nav', 'admin.plans.index', 'plans', 'manage tenants', ['admin.plans.*'], 20),
            new MenuItem('settings', 'plans.subscriptionNav', 'subscription.show', 'subscription', 'manage settings', ['subscription.*'], 10),
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
        ];
    }

    public function seeders(): array
    {
        return [PlanSeeder::class];
    }

    protected function bindings(): void
    {
        $this->app->singleton(Entitlements::class);
        $this->app->bind(PlanLimits::class, EntitlementsPlanLimits::class);
    }

    /**
     * Expired subscriptions are held back on every web page, and the "module" alias lets a route require a plan feature.
     */
    public function boot(): void
    {
        parent::boot();

        TenantFactory::$hooks[] = fn (Tenant $tenant) => GivesTenantsAPlan::for($tenant);

        Tenant::resolveRelationUsing('subscriptions', fn (Tenant $tenant) => $tenant->hasMany(Subscription::class, 'tenant_id'));
        Tenant::resolveRelationUsing('subscription', fn (Tenant $tenant) => $tenant->hasOne(Subscription::class, 'tenant_id')->latestOfMany());

        $router = $this->app->make('router');
        $router->aliasMiddleware('subscription.usable', EnsureSubscriptionIsUsable::class);

        $kernel = $this->app->make(Kernel::class);
        $kernel->appendMiddlewareToGroup('web', EnsureSubscriptionIsUsable::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, EnsureSubscriptionIsUsable::class);
    }

    public function sharedProps(): array
    {
        return [
            'plan' => fn (Request $request, ?User $user) => $user && $user->tenant_id ? $this->planSummary($user->tenant_id) : null,
        ];
    }

    /**
     * @return array{name: string, status: string, usable: bool, trial_ends_at: ?string, ends_at: ?string, modules: list<string>}|null
     */
    private function planSummary(int $tenantId): ?array
    {
        $entitlements = $this->app->make(Entitlements::class);
        $subscription = $entitlements->subscription($tenantId);

        return $subscription ? [
            'name' => $subscription->plan->name,
            'status' => $subscription->effectiveStatus()->value,
            'usable' => $subscription->isUsable(),
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'modules' => $entitlements->modules($tenantId),
        ] : null;
    }
}
