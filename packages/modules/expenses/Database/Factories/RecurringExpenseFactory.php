<?php

namespace App\Modules\Expenses\Database\Factories;

use App\Modules\Expenses\Enums\RecurrenceFrequency;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\RecurringExpense;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->startOfMonth()->toDateString();

        return [
            'created_by' => User::factory(),
            'tenant_id' => fn (array $attributes) => User::find($attributes['created_by'])->tenant_id,
            'expense_category_id' => fn (array $attributes) => ExpenseCategory::factory()->create(['tenant_id' => $attributes['tenant_id']])->id,
            'amount' => 500,
            'description' => 'Rent',
            'frequency' => RecurrenceFrequency::Monthly,
            'starts_on' => $start,
            'next_run_on' => $start,
            'runs_count' => 0,
            'is_active' => true,
        ];
    }
}
