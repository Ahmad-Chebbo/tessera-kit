<?php

namespace App\Modules\Team\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait EmployeeProfileRules
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function profileRules(?int $companyId, ?int $ignoreUserId = null): array
    {
        return [
            'employee_number' => [
                'nullable', 'string', 'max:32',
                Rule::unique('employee_profiles', 'employee_number')
                    ->where('company_id', $companyId)
                    ->ignore($ignoreUserId, 'user_id'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'hire_date' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
