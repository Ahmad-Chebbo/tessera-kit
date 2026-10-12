<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;

/**
 * What the tenant factory does when the Subscription module is installed: every tenant made for a test gets a plan
 * with all the modules, so nothing is held back by a missing subscription.
 */
class GivesTenantsAPlan
{
    public static function for(Tenant $tenant): void
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'factory-all-modules'],
            ['name' => 'All modules', 'modules' => Module::values(), 'billing_interval' => Plan::LIFETIME, 'price' => 0],
        );

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDay(),
        ]);
    }
}
