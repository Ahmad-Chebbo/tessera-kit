<?php

namespace App\Modules\Company\Actions;

use App\Modules\Company\Enums\LocationType;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Company\Services\RoleTemplates;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateCompanyAction
{
    public function __construct(
        private readonly RoleTemplates $roles,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Creates a company with a main branch and location, its owner account and a subscription to the chosen plan.
     *
     * @param  array<string, mixed>  $data  name, currency, plan_id, admin_name, admin_email, admin_password and optional contact fields.
     */
    public function execute(array $data): Company
    {
        return DB::transaction(function () use ($data): Company {
            $company = Company::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'currency' => $data['currency'],
                'tax_rate' => $data['tax_rate'] ?? 0,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $branch = Branch::create([
                'company_id' => $company->id,
                'name' => 'Main Branch',
                'is_active' => true,
            ]);

            Location::create([
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'name' => 'Main Branch',
                'type' => LocationType::Branch,
                'is_active' => true,
            ]);

            $owner = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => Hash::make($data['admin_password']),
                'company_id' => $company->id,
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            $owner->assignRole($this->roles->provision($company)['Owner']);

            $this->subscriptions->start($company, Plan::findOrFail($data['plan_id']));

            return $company;
        });
    }
}
