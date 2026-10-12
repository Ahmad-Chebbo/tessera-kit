<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Company\Models\Branch;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;

/**
 * What a company is allowed to do under its plan. Results are remembered for the request and dropped
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

    public function subscription(int $companyId): ?Subscription
    {
        if (! array_key_exists($companyId, $this->subscriptions)) {
            $this->subscriptions[$companyId] = Subscription::query()
                ->with('plan')
                ->where('company_id', $companyId)
                ->latest('id')
                ->first();
        }

        return $this->subscriptions[$companyId];
    }

    public function isUsable(int $companyId): bool
    {
        return $this->subscription($companyId)?->isUsable() ?? false;
    }

    public function hasModule(int $companyId, Module|string $module): bool
    {
        return in_array($module instanceof Module ? $module->value : $module, $this->modules($companyId), true);
    }

    /**
     * The modules switched on for a company: the plan's modules, adjusted by the company's overrides.
     *
     * @return list<string> Plan feature keys.
     */
    public function modules(int $companyId): array
    {
        $subscription = $this->subscription($companyId);

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
    public function limit(int $companyId, string $key): ?int
    {
        $plan = $this->subscription($companyId)?->plan;

        return $key === 'users' ? $plan?->max_users : $plan?->max_branches;
    }

    /**
     * @param  'users'|'branches'  $key
     */
    public function usage(int $companyId, string $key): int
    {
        return $key === 'users'
            ? User::query()->where('company_id', $companyId)->count()
            : Branch::query()->where('company_id', $companyId)->count();
    }

    /**
     * @param  'users'|'branches'  $key
     */
    public function hasRoomFor(int $companyId, string $key): bool
    {
        $limit = $this->limit($companyId, $key);

        return $limit === null || $this->usage($companyId, $key) < $limit;
    }
}
