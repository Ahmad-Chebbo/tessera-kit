<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Services\OrganizationTree;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage organization') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;
        $department = $this->route('department');

        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('departments', 'name')->where('tenant_id', $tenantId)->ignore($department?->id),
            ],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('departments', 'id')->where('tenant_id', $tenantId),
                function (string $attribute, mixed $value, Closure $fail) use ($department): void {
                    if ($value !== null && $department !== null && app(OrganizationTree::class)->departmentLoops($department->id, (int) $value)) {
                        $fail('A department cannot sit under itself or one of its own departments.');
                    }
                },
            ],
            'head_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['name.unique' => 'A department with this name already exists.'];
    }
}
