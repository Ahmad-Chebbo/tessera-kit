<?php

namespace App\Modules\Expenses\Database\Factories;

use App\Modules\Company\Models\Company;
use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->word(),
            'type' => ExpenseType::Operating,
            'affects_profit' => true,
            'is_active' => true,
        ];
    }

    public function ofType(ExpenseType $type): static
    {
        return $this->state(fn () => ['type' => $type, 'affects_profit' => $type->affectsProfitByDefault()]);
    }
}
