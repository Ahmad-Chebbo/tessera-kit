<?php

namespace App\Modules\Expenses\Http\Requests;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Tessera\Core\Contracts\PurchaseLookup;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->route('expense') ? 'edit expenses' : 'create expenses') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $companyId = $this->user()->company_id;
        $expense = $this->route('expense');
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        return [
            'expense_category_id' => ['required', 'integer', $inCompany('expense_categories')->where('is_active', true)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'expense_date' => ['required', 'date_format:Y-m-d'],
            'description' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', $inCompany('branches')],
            'employee_id' => ['nullable', 'integer', $inCompany('users')],
            'purchase_id' => app(PurchaseLookup::class)->available()
                ? [
                    'nullable', 'integer',
                    Rule::exists('purchases', 'id')->where('company_id', $companyId)->where('status', 'received'),
                    Rule::unique('expenses', 'purchase_id')->ignore($expense?->id),
                ]
                : ['prohibited'],
            'spread_months' => ['nullable', 'integer', 'between:2,120'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'remove_receipt' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $category = ExpenseCategory::query()->where('company_id', $this->user()->company_id)->find($this->input('expense_category_id'));

            if ($this->filled('spread_months') && $category?->type !== ExpenseType::Assets) {
                $validator->errors()->add('spread_months', 'Only asset expenses can be spread over months.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_id.unique' => 'This purchase is already recorded as an expense.',
            'purchase_id.exists' => 'Only received purchases can be recorded as expenses.',
            'receipt.mimes' => 'The receipt must be an image or a PDF.',
            'receipt.max' => 'The receipt may not be larger than 5 MB.',
        ];
    }
}
