<?php

namespace App\Modules\Subscription\Http\Requests;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage companies') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency' => ['required', 'string', 'size:3'],
            'billing_interval' => ['required', Rule::in(Plan::INTERVALS)],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', Rule::in(Module::values())],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
