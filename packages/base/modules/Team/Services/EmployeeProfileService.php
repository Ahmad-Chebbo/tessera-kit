<?php

namespace App\Modules\Team\Services;

use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\User;
use Illuminate\Support\Arr;

class EmployeeProfileService
{
    /**
     * Create or update the profile of an employee. A blank employee number keeps the current one,
     * or generates the next free number for the tenant when there is none yet.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(User $user, array $data): EmployeeProfile
    {
        $fields = Arr::only($data, EmployeeProfile::EDITABLE);
        $profile = $user->employeeProfile ?? new EmployeeProfile([
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);

        if (blank($fields['employee_number'] ?? null)) {
            unset($fields['employee_number']);

            if (! $profile->exists) {
                $fields['employee_number'] = $this->nextNumber($user->tenant_id);
            }
        }

        $profile->fill($fields)->save();
        $user->setRelation('employeeProfile', $profile);

        return $profile;
    }

    public function nextNumber(?int $tenantId): string
    {
        $last = EmployeeProfile::query()
            ->where('tenant_id', $tenantId)
            ->where('employee_number', 'like', 'EMP-%')
            ->orderByDesc('employee_number')
            ->value('employee_number');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'EMP-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
