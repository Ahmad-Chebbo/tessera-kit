<?php

namespace App\Modules\Subscription\Http\Requests;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySubscriptionRequest extends FormRequest
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
        return [
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'status' => ['required', Rule::enum(SubscriptionStatus::class)],
            'trial_ends_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'module_overrides' => ['nullable', 'array'],
            'module_overrides.*' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Only known modules survive, and only the ones set explicitly.
     *
     * @return array<string, bool>
     */
    public function overrides(): array
    {
        return collect($this->validated('module_overrides') ?? [])
            ->filter(fn ($value, string $module): bool => $value !== null && in_array($module, Module::values(), true))
            ->map(fn ($value): bool => (bool) $value)
            ->all();
    }
}
