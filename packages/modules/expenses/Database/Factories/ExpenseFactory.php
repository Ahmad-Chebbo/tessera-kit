<?php

namespace App\Modules\Expenses\Database\Factories;

use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'created_by' => User::factory(),
            'company_id' => fn (array $attributes) => User::find($attributes['created_by'])->company_id,
            'expense_category_id' => fn (array $attributes) => ExpenseCategory::factory()->create(['company_id' => $attributes['company_id']])->id,
            'amount' => 100,
            'description' => fake()->sentence(3),
            'expense_date' => now()->toDateString(),
        ];
    }
}
