<?php

namespace App\Modules\Organization\Http\Requests;

use App\Modules\Organization\Services\OrganizationTree;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');

        return [
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'position_id' => ['nullable', 'integer', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'manager_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
                function (string $attribute, mixed $value, Closure $fail) use ($employee): void {
                    if ($value === null) {
                        return;
                    }

                    if ((int) $value === $employee->id) {
                        $fail('Someone cannot be their own manager.');
                    } elseif (app(OrganizationTree::class)->reportingLoops($employee->id, (int) $value)) {
                        $fail('That would make the manager report to their own report.');
                    }
                },
            ],
        ];
    }
}
