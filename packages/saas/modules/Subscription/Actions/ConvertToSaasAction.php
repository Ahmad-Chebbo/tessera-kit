<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\RoleTemplates;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Turns a project that ran as a single business into one that serves many tenants. The business becomes the first
 * tenant: it keeps working, with its own roles and a plan that has every installed module, and the platform admin who
 * will manage tenants can be created. Running it again changes nothing that is already done.
 */
class ConvertToSaasAction
{
    public function __construct(
        private readonly PermissionSynchronizer $permissions,
        private readonly RoleTemplates $templates,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * @return array{
     *     permissions_created: int,
     *     plan_modules: list<string>,
     *     tenants: list<array{name: string, roles_moved: int, subscribed: bool}>,
     *     admin: array{email: string, created: bool, password: string|null}|null
     * }
     *
     * @throws InvalidArgumentException when the admin's email belongs to someone who is in a tenant.
     */
    public function execute(?string $adminEmail = null, string $adminName = 'Admin', ?string $adminPassword = null): array
    {
        return DB::transaction(function () use ($adminEmail, $adminName, $adminPassword): array {
            $single = Tenant::query()->count() === 1;

            // The roles are copied before the permissions are synced, so the copies keep what the business made of them.
            $moved = Tenant::query()->orderBy('id')->get()->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $this->giveRoles($tenant, $single)]);

            $synced = $this->permissions->sync();
            $plan = $this->allModulesPlan();

            $tenants = Tenant::query()->orderBy('id')->get()->map(function (Tenant $tenant) use ($plan, $moved): array {
                $subscribed = $tenant->subscription()->doesntExist();

                if ($subscribed) {
                    $this->subscriptions->start($tenant, $plan);
                }

                return ['name' => $tenant->name, 'roles_moved' => $moved[$tenant->id], 'subscribed' => $subscribed];
            })->all();

            return [
                'permissions_created' => count($synced['created']),
                'plan_modules' => $plan->modules,
                'tenants' => $tenants,
                'admin' => $adminEmail === null ? null : $this->platformAdmin($adminEmail, $adminName, $adminPassword),
            ];
        });
    }

    /**
     * The plan the first tenant is put on: the legacy one, brought up to date with every module installed now.
     */
    private function allModulesPlan(): Plan
    {
        $plan = Plan::query()->firstOrCreate(
            ['slug' => 'legacy'],
            ['name' => 'Legacy (all modules)', 'description' => 'Given to tenants that existed before plans were introduced.', 'price' => 0, 'billing_interval' => Plan::LIFETIME, 'modules' => Module::values(), 'is_active' => false],
        );

        $plan->update(['modules' => Module::values()]);

        return $plan;
    }

    /**
     * Gives the tenant its own copy of the standard roles and moves its users onto them. Roles a single business made
     * for itself become the tenant's own when it is the only one.
     */
    private function giveRoles(Tenant $tenant, bool $single): int
    {
        $copies = $this->templates->provision($tenant);

        if ($single) {
            Role::query()->whereNull('tenant_id')->whereNotIn('name', [...RoleTemplates::NAMES, 'Admin'])->update(['tenant_id' => $tenant->id]);
        }

        $moved = 0;

        User::query()->where('tenant_id', $tenant->id)->with('roles')->each(function (User $user) use ($copies, &$moved): void {
            $roles = $user->roles->map(fn (Role $role): Role => $role->tenant_id === null && $copies->has($role->name) ? $copies[$role->name] : $role);

            if ($roles->pluck('id')->sort()->values()->all() !== $user->roles->pluck('id')->sort()->values()->all()) {
                $user->syncRoles($roles->unique('id')->values()->all());
                $moved++;
            }
        });

        return $moved;
    }

    /**
     * @return array{email: string, created: bool, password: string|null}
     */
    private function platformAdmin(string $email, string $name, ?string $password): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->tenant_id !== null) {
            throw new InvalidArgumentException("{$email} belongs to a tenant. The platform admin must be someone who belongs to none.");
        }

        $created = $user === null;
        $password = $created ? ($password ?? Str::password(16, symbols: false)) : null;

        $user ??= User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'is_active' => true]);
        $user->assignRole('Admin');

        return ['email' => $email, 'created' => $created, 'password' => $created ? $password : null];
    }
}
