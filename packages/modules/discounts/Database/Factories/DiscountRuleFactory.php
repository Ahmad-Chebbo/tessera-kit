<?php

namespace App\Modules\Discounts\Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Discounts\Models\DiscountRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiscountRule>
 */
class DiscountRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->words(3, true),
            'is_active' => true,
            'priority' => 100,
            'stackable' => true,
            'conditions' => ['type' => 'group', 'logic' => 'all', 'children' => []],
            'action_type' => DiscountAction::Percent,
            'action_value' => 10,
            'action_config' => null,
        ];
    }

    /**
     * A rule whose only condition is a single comparison.
     *
     * @param  array<string, mixed>  $params
     */
    public function withCondition(string $field, string $operator, mixed $value, array $params = []): static
    {
        return $this->state(fn () => ['conditions' => [
            'type' => 'group', 'logic' => 'all',
            'children' => [['type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value, 'params' => $params]],
        ]]);
    }
}
