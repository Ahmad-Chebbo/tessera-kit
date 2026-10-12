<?php

namespace App\Modules\Team\Actions;

use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tessera\Engine\ModuleRegistry;

/**
 * Creates the first account of a project: the permissions and default roles are synced, then the account gets the Owner
 * role, or the platform Admin role when the project serves many tenants. Running it for an email that exists gives that
 * user the role and leaves their password alone.
 */
class InstallFirstAccountAction
{
    public function __construct(
        private readonly PermissionSynchronizer $permissions,
        private readonly ModuleRegistry $modules,
    ) {}

    /**
     * @return array{email: string, role: string, created: bool, password: string|null}
     *
     * @throws InvalidArgumentException when the account cannot take the role.
     */
    public function execute(string $email, string $name = 'Admin', ?string $password = null): array
    {
        return DB::transaction(function () use ($email, $name, $password): array {
            $this->permissions->sync();

            $saas = $this->modules->has('subscription');
            $role = $saas ? 'Admin' : 'Owner';
            $user = User::query()->where('email', $email)->first();

            if ($saas && $user !== null && $user->tenant_id !== null) {
                throw new InvalidArgumentException("{$email} belongs to a tenant. The platform admin must be someone who belongs to none.");
            }

            $created = $user === null;
            $password = $created ? ($password ?? Str::password(16, symbols: false)) : null;

            $user ??= User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'is_active' => true]);

            if (! $saas) {
                Role::findByName($role)->syncPermissions(Permission::all());
            }

            $user->assignRole($role);

            return ['email' => $email, 'role' => $role, 'created' => $created, 'password' => $created ? $password : null];
        });
    }
}
