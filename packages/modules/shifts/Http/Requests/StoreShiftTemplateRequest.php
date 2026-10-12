<?php

namespace App\Modules\Shifts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShiftTemplateRequest extends FormRequest
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
        $template = $this->route('template');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('shift_templates', 'name')->where('tenant_id', $this->user()->tenant_id)->ignore($template?->id),
            ],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'different:starts_at'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'A shift with this name already exists.',
            'ends_at.different' => 'A shift must end at a different time than it starts.',
        ];
    }
}
