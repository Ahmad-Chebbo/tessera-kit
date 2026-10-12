<?php

namespace App\Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage shifts') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'shift_template_id' => ['required', 'integer', Rule::exists('shift_templates', 'id')->where('tenant_id', $tenantId)->where('is_active', true)],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
