<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /**
     * Put a tenant on a plan: a trial when the plan has one, otherwise an active period that runs for one interval.
     */
    public function start(Tenant $tenant, Plan $plan): Subscription
    {
        $this->endCurrent($tenant);

        $trialing = $plan->trial_days > 0;

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $trialing ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'starts_at' => now(),
            'trial_ends_at' => $trialing ? now()->addDays($plan->trial_days) : null,
            'ends_at' => $trialing ? null : $this->periodEnd($plan),
        ]);
    }

    /**
     * Close the current subscription so a new one can take over and the old row stays as history.
     */
    public function endCurrent(Tenant $tenant): void
    {
        $current = $tenant->subscription()->first();

        if ($current && $current->status !== SubscriptionStatus::Expired) {
            $current->update(['status' => SubscriptionStatus::Canceled, 'canceled_at' => now(), 'ends_at' => now()]);
        }
    }

    public function periodEnd(Plan $plan): ?Carbon
    {
        return match ($plan->billing_interval) {
            Plan::MONTHLY => now()->addMonth(),
            Plan::YEARLY => now()->addYear(),
            default => null,
        };
    }
}
