<?php

namespace App\Modules\Team\Http\Requests;

use App\Modules\Team\Http\Requests\Concerns\EmployeeProfileRules;
use App\Modules\Team\Services\AssignableRoles;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Tessera\Core\Contracts\BranchScope;

class StoreUserRequest extends FormRequest
{
    use EmployeeProfileRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create users') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', Rule::in(app(AssignableRoles::class)->for($user))],
            'is_active' => ['boolean'],
            ...(app(BranchScope::class)->available() ? ['branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $user->tenant_id)]] : []),
            ...(app(TeamFeatures::class)->employeeProfiles() ? $this->profileRules($user->tenant_id) : []),
        ];
    }
}
