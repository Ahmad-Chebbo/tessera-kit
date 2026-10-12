<?php

namespace App\Modules\Subscription\Actions;

use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\RoleTemplates;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Enums\LocationType;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateTenantAction
{
    public function __construct(
        private readonly RoleTemplates $roles,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Creates a tenant with a main branch and location, its owner account and a subscription to the chosen plan.
     *
     * @param  array<string, mixed>  $data  name, currency, plan_id, admin_name, admin_email, admin_password and optional contact fields.
     */
    public function execute(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $branch = Branch::create([
                'tenant_id' => $tenant->id,
                'name' => 'Main Branch',
                'is_active' => true,
            ]);

            Location::create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'name' => 'Main Branch',
                'type' => LocationType::Branch,
                'is_active' => true,
            ]);

            $owner = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            $owner->assignRole($this->roles->provision($tenant)['Owner']);

            $this->subscriptions->start($tenant, Plan::findOrFail($data['plan_id']));

            return $tenant;
        });
    }
}
