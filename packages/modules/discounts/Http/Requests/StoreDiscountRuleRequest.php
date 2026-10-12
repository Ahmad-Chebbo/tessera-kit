<?php

namespace App\Modules\Discounts\Http\Requests;

use App\Modules\Discounts\Engine\ConditionTreeValidator;
use App\Modules\Discounts\Engine\RuleExpression;
use App\Modules\Discounts\Enums\DiscountAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDiscountRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage discounts') ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('coupon_code')) {
            $this->merge(['coupon_code' => strtoupper(trim((string) $this->input('coupon_code')))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rule = $this->route('rule');
        $action = $this->input('action_type');

        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'priority' => ['required', 'integer', 'between:-9999,9999'],
            'stackable' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'conditions' => ['nullable', 'array'],
            'expression' => ['nullable', 'string', 'max:'.RuleExpression::MAX_LENGTH],
            'action_type' => ['required', Rule::in(DiscountAction::values())],
            'action_value' => ['required', 'numeric', 'min:0', 'max:'.($action === DiscountAction::Percent->value ? '100' : '99999999')],
            'action_config' => ['nullable', 'array'],
            'action_config.apply_to' => ['nullable', Rule::in(['each_unit', 'each_line', 'total'])],
            'action_config.cap_amount' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'action_config.buy_quantity' => [Rule::requiredIf(in_array($action, [DiscountAction::BuyXGetY->value, DiscountAction::BuyGetItem->value], true)), 'nullable', 'numeric', 'min:1', 'max:100000'],
            'action_config.get_quantity' => [Rule::requiredIf(in_array($action, [DiscountAction::BuyXGetY->value, DiscountAction::BuyGetItem->value], true)), 'nullable', 'numeric', 'min:1', 'max:100000'],
            'action_config.get_discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'action_config.buy_product_ids' => [Rule::requiredIf($action === DiscountAction::BuyGetItem->value), 'nullable', 'array', 'min:1', 'max:50'],
            'action_config.buy_product_ids.*' => ['integer', Rule::exists('products', 'id')->where('company_id', $this->user()->company_id)],
            'action_config.get_product_ids' => [Rule::requiredIf($action === DiscountAction::BuyGetItem->value), 'nullable', 'array', 'min:1', 'max:50'],
            'action_config.get_product_ids.*' => ['integer', Rule::exists('products', 'id')->where('company_id', $this->user()->company_id)],
            'action_config.max_sets' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'coupon_code' => [
                'nullable', 'string', 'max:50', 'alpha_dash',
                Rule::unique('discount_rules', 'coupon_code')->where('company_id', $this->user()->company_id)->ignore($rule?->id),
            ],
            'usage_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'per_customer_limit' => ['nullable', 'integer', 'min:1', 'max:100000000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (app(ConditionTreeValidator::class)->errors($this->input('conditions'), $this->user()->company_id) as $error) {
                $validator->errors()->add('conditions', $error);
            }

            $expression = trim((string) $this->input('expression'));

            if ($expression !== '' && ($error = app(RuleExpression::class)->validate($expression)) !== null) {
                $validator->errors()->add('expression', $error);
            }

            if ($this->input('per_customer_limit') && ! $this->filled('coupon_code')) {
                $validator->errors()->add('per_customer_limit', 'Usage limits need a coupon code.');
            }
        }];
    }

    /**
     * The saved attributes, with settings that do not belong to the chosen action dropped.
     *
     * @return array<string, mixed>
     */
    public function ruleAttributes(): array
    {
        $data = $this->validated();
        $action = DiscountAction::from($data['action_type']);
        $config = $data['action_config'] ?? [];

        $allowed = match ($action) {
            DiscountAction::Percent, DiscountAction::Price => ['cap_amount'],
            DiscountAction::Fixed => ['apply_to', 'cap_amount'],
            DiscountAction::BuyXGetY => ['buy_quantity', 'get_quantity', 'get_discount_percent', 'cap_amount'],
            DiscountAction::BuyGetItem => ['buy_product_ids', 'get_product_ids', 'buy_quantity', 'get_quantity', 'get_discount_percent', 'max_sets', 'cap_amount'],
        };

        $data['action_config'] = array_filter(array_intersect_key($config, array_flip($allowed)), fn ($value) => $value !== null && $value !== '') ?: null;
        $data['expression'] = trim((string) ($data['expression'] ?? '')) ?: null;
        $data['coupon_code'] = ($data['coupon_code'] ?? null) ?: null;
        $data['conditions'] = $data['conditions'] ?? null;
        $data['is_active'] = $this->boolean('is_active');
        $data['stackable'] = $this->boolean('stackable');

        return $data;
    }
}
