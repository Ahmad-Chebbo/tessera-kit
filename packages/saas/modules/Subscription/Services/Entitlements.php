<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;

/**
 * What a tenant is allowed to do under its plan. Results are remembered for the request and dropped
 * whenever a plan or subscription changes.
 */
class Entitlements
{
    /**
     * @var array<int, Subscription|null>
     */
    private array $subscriptions = [];

    public function flush(): void
    {
        $this->subscriptions = [];
    }

    public function subscription(int $tenantId): ?Subscription
    {
        if (! array_key_exists($tenantId, $this->subscriptions)) {
            $this->subscriptions[$tenantId] = Subscription::query()
                ->with('plan')
                ->where('tenant_id', $tenantId)
                ->latest('id')
                ->first();
        }

        return $this->subscriptions[$tenantId];
    }

    public function isUsable(int $tenantId): bool
    {
        return $this->subscription($tenantId)?->isUsable() ?? false;
    }

    public function hasModule(int $tenantId, Module|string $module): bool
    {
        return in_array($module instanceof Module ? $module->value : $module, $this->modules($tenantId), true);
    }

    /**
     * The modules switched on for a tenant: the plan's modules, adjusted by the tenant's overrides.
     *
     * @return list<string> Plan feature keys.
     */
    public function modules(int $tenantId): array
    {
        $subscription = $this->subscription($tenantId);

        if ($subscription === null || ! $subscription->isUsable()) {
            return [];
        }

        $overrides = $subscription->module_overrides ?? [];

        return collect(Module::values())
            ->filter(fn (string $key): bool => $overrides[$key] ?? $subscription->plan->includes($key))
            ->values()
            ->all();
    }

    /**
     * @param  'users'|'branches'  $key
     */
    public function limit(int $tenantId, string $key): ?int
    {
        $plan = $this->subscription($tenantId)?->plan;

        return $key === 'users' ? $plan?->max_users : $plan?->max_branches;
    }

    /**
     * @param  'users'|'branches'  $key
     */
    public function usage(int $tenantId, string $key): int
    {
        return $key === 'users'
            ? User::query()->where('tenant_id', $tenantId)->count()
            : Branch::query()->where('tenant_id', $tenantId)->count();
    }

    /**
     * @param  'users'|'branches'  $key
     */
    public function hasRoomFor(int $tenantId, string $key): bool
    {
        $limit = $this->limit($tenantId, $key);

        return $limit === null || $this->usage($tenantId, $key) < $limit;
    }
}
