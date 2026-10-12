<?php

namespace App\Modules\Team\Http\Requests;

use App\Modules\Team\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Tessera\Core\Contracts\Tenancy;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage roles') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $role = $this->route('role');
        $tenancy = app(Tenancy::class);

        return [
            'name' => [
                'required', 'string', 'max:100', Rule::notIn(['Admin']),
                $tenancy->scopeRule(Rule::unique('roles', 'name'), $tenancy->roleOwner($this->user()))->ignore($role?->id),
            ],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', Rule::in($this->grantablePermissions())],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $role = $this->route('role');

            if ($role instanceof Role && $this->user()->roles->contains('id', $role->id) && ! in_array('manage roles', $this->input('permissions', []), true)) {
                $validator->errors()->add('permissions', 'You cannot remove role management from your own role.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Your tenant already has a role with this name.',
            'name.not_in' => 'This name is reserved.',
            'permissions.*.in' => 'You can only grant permissions you hold yourself.',
        ];
    }

    /**
     * Permissions the signed-in user may hand out: the ones they hold, never platform administration.
     *
     * @return list<string>
     */
    public function grantablePermissions(): array
    {
        return $this->user()->getAllPermissions()->pluck('name')->reject(fn (string $name): bool => $name === 'manage tenants')->values()->all();
    }
}
