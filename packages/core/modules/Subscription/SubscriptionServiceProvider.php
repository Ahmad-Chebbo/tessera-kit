<?php

namespace App\Modules\Subscription;

use App\Modules\Subscription\Database\Seeders\PlanSeeder;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;

/**
 * Plans and subscriptions. A core module that works with Company and Team.
 */
class SubscriptionServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'subscription',
            name: 'Subscription',
            requires: [],
            core: true,
        );
    }

    public function menu(): array
    {
        return [
            new MenuItem('admin', 'plans.nav', 'admin.plans.index', 'plans', 'manage companies', ['admin.plans.*'], 20),
            new MenuItem('settings', 'plans.subscriptionNav', 'subscription.show', 'subscription', 'manage settings', ['subscription.*'], 10),
        ];
    }

    public function seeders(): array
    {
        return [PlanSeeder::class];
    }
}
