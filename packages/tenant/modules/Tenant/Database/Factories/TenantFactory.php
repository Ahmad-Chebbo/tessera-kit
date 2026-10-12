<?php

namespace App\Modules\Tenant\Database\Factories;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    /**
     * What other modules do with each tenant the factory makes, for example the Subscription module gives it a plan.
     *
     * @var list<\Closure(Tenant): void>
     */
    public static array $hooks = [];

    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant): void {
            foreach (self::$hooks as $callback) {
                $callback($tenant);
            }
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
