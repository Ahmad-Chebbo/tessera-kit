<?php

namespace App\Modules\Subscription\Database\Seeders;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'starter', 'name' => 'Starter', 'price' => 19, 'trial_days' => 14, 'max_users' => 3, 'max_branches' => 1, 'sort_order' => 1,
                'description' => 'Sell at the counter and keep a catalog.',
                'modules' => [Module::Pos, Module::Catalog, Module::Customers]],
            ['slug' => 'growth', 'name' => 'Growth', 'price' => 49, 'trial_days' => 14, 'max_users' => 15, 'max_branches' => 3, 'sort_order' => 2,
                'description' => 'Stock, purchasing, expenses and reporting for a growing shop.',
                'modules' => [Module::Pos, Module::Catalog, Module::Customers, Module::Inventory, Module::Expenses, Module::Reports, Module::Branches]],
            ['slug' => 'pro', 'name' => 'Pro', 'price' => 99, 'trial_days' => 14, 'max_users' => null, 'max_branches' => null, 'sort_order' => 3,
                'description' => 'Everything, including shifts, attendance and custom roles.',
                'modules' => Module::cases()],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], [
                ...$plan,
                'modules' => array_map(fn (Module $module): string => $module->value, $plan['modules']),
                'currency' => 'USD',
                'billing_interval' => Plan::MONTHLY,
                'is_active' => true,
            ]);
        }
    }
}
