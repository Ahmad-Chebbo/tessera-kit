<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Enums\RecurrenceFrequency;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRecurringExpenseRequest extends FormRequest
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
        $tenantId = $this->user()->tenant_id;
        $existing = $this->route('recurring');
        $inTenant = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenantId);

        return [
            'expense_category_id' => ['required', 'integer', $inTenant('expense_categories')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'description' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', $inTenant('branches')],
            'employee_id' => ['nullable', 'integer', $inTenant('users')],
            'frequency' => [$existing ? 'nullable' : 'required', Rule::enum(RecurrenceFrequency::class)],
            'starts_on' => [$existing ? 'nullable' : 'required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.($existing ? $existing->starts_on->toDateString() : 'starts_on')],
            'spread_months' => ['nullable', 'integer', 'between:2,120'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = ExpenseCategory::query()->where('tenant_id', $this->user()->tenant_id)->find($this->input('expense_category_id'));

            if ($this->filled('spread_months') && $category?->type !== ExpenseType::Assets) {
                $validator->errors()->add('spread_months', 'Only asset expenses can be spread over months.');
            }
        }];
    }
}
