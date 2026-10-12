<?php

namespace App\Modules\Tenant\Database\Factories;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
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
        });
    }

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'USD',
            'locale' => 'en',
            'tax_rate' => 0,
            'timezone' => 'UTC',
            'receipt_header' => fake()->sentence(),
            'receipt_footer' => 'Thank you for your purchase!',
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'is_active' => true,
        ];
    }
}
