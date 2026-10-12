<?php

namespace App\Modules\Team\Http\Requests;

use App\Modules\Team\Http\Requests\Concerns\EmployeeProfileRules;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\AssignableRoles;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Core\Contracts\Tenancy;

class UpdateUserRequest extends FormRequest
{
    use EmployeeProfileRules;

    public function authorize(): bool
    {
        $actor = $this->user();
        /** @var User $target */
        $target = $this->route('user');

        return $actor !== null
            && $actor->can('edit users')
            && app(Tenancy::class)->sameTenant($target, $actor)
            && ! ($target->isSuperAdmin() && ! $actor->isSuperAdmin());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $actor = $this->user();
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($target->id)],
            'password' => ['nullable', Password::defaults()],
            'role' => ['required', Rule::in(app(AssignableRoles::class)->for($actor))],
            'is_active' => ['boolean'],
            ...(app(BranchScope::class)->available() ? ['branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $actor->tenant_id)]] : []),
            ...(app(TeamFeatures::class)->employeeProfiles() ? $this->profileRules($actor->tenant_id, $target->id) : []),
        ];
    }
}
