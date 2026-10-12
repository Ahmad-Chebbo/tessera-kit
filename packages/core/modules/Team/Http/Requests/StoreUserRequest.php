<?php

namespace App\Modules\Team\Http\Requests;

use App\Modules\Team\Http\Requests\Concerns\EmployeeProfileRules;
use App\Modules\Team\Services\AssignableRoles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('company_id', $user->company_id)],
            'role' => ['required', Rule::in(app(AssignableRoles::class)->for($user))],
            'is_active' => ['boolean'],
            ...$this->profileRules($user->company_id),
        ];
    }
}
