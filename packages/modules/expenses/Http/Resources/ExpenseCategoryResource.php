<?php

namespace App\Modules\Expenses\Http\Resources;

use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ExpenseCategory
 */
class ExpenseCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'affects_profit' => $this->affects_profit,
            'is_active' => $this->is_active,
        ];
    }
}
