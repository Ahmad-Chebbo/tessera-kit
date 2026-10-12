<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Enums\ExpenseType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('edit expenses') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->where('tenant_id', $this->user()->tenant_id)->ignore($this->route('category')?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ExpenseType::class)],
            'affects_profit' => ['nullable', 'boolean'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Attributes to save. A category that does not say otherwise follows what its type usually does.
     *
     * @return array<string, mixed>
     */
    public function attributesToSave(): array
    {
        $data = $this->validated();
        $data['affects_profit'] = $this->has('affects_profit') && $this->input('affects_profit') !== null
            ? $this->boolean('affects_profit')
            : ExpenseType::from($data['type'])->affectsProfitByDefault();
        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }
}
