<?php

namespace App\Modules\Expenses\Http\Resources;

use App\Modules\Expenses\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Expense
 */
class ExpenseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'type' => $this->category->type->value,
                'affects_profit' => $this->category->affects_profit,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch?->only(['id', 'name'])),
            'employee' => $this->whenLoaded('employee', fn () => $this->employee?->only(['id', 'name'])),
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'expense_date' => $this->expense_date->toDateString(),
            'spread_months' => $this->spread_months,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'purchase_id' => $this->purchase_id,
            'from_recurring' => $this->recurring_expense_id !== null,
            'has_receipt' => $this->getFirstMedia('receipt') !== null,
            'receipt_url' => $this->getFirstMedia('receipt') ? route('api.v1.expenses.receipt', $this->resource) : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
