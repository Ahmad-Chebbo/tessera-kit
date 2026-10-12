<?php

namespace App\Modules\Subscription\Database\Factories;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => Str::title($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->sentence(),
            'price' => 29,
            'currency' => 'USD',
            'billing_interval' => Plan::MONTHLY,
            'trial_days' => 0,
            'modules' => Module::values(),
            'max_users' => null,
            'max_branches' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  list<Module>  $modules
     */
    public function withModules(array $modules): static
    {
        return $this->state(fn () => ['modules' => array_map(fn (Module $module): string => $module->value, $modules)]);
    }
}
