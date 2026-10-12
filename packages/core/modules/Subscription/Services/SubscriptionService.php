<?php

namespace App\Modules\Subscription\Services;

use App\Modules\Company\Models\Company;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /**
     * Put a company on a plan: a trial when the plan has one, otherwise an active period that runs for one interval.
     */
    public function start(Company $company, Plan $plan): Subscription
    {
        $this->endCurrent($company);

        $trialing = $plan->trial_days > 0;

        return Subscription::create([
            'company_id' => $company->id,
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
    public function endCurrent(Company $company): void
    {
        $current = $company->subscription()->first();

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
