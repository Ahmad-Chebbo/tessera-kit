<?php

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Team\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Contracts\CustomerActivity;
use Tessera\Core\Contracts\Defaults\NoCustomerActivity;

it('lists customers without the order history when no module provides it', function () {
    app()->bind(CustomerActivity::class, NoCustomerActivity::class);

    $company = Company::factory()->create();
    $user = User::factory()->create(['company_id' => $company->id, 'branch_id' => Branch::factory()->create(['company_id' => $company->id])->id])
        ->givePermissionTo(Permission::findOrCreate('view customers'));
    Customer::create(['company_id' => $company->id, 'name' => 'Walk-in', 'is_active' => true]);

    $this->actingAs($user)->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->where('ordersAvailable', false)
            ->where('customers.data.0.name', 'Walk-in')
            ->where('customers.data.0.orders_count', 0));
});
