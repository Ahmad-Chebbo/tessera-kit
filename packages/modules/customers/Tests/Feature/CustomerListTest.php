<?php

use App\Modules\Customers\Models\Customer;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Contracts\CustomerActivity;
use Tessera\Core\Contracts\Defaults\NoCustomerActivity;

it('lists customers without the order history when no module provides it', function () {
    app()->bind(CustomerActivity::class, NoCustomerActivity::class);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => Branch::factory()->create(['tenant_id' => $tenant->id])->id])
        ->givePermissionTo(Permission::findOrCreate('view customers'));
    Customer::create(['tenant_id' => $tenant->id, 'name' => 'Walk-in', 'is_active' => true]);

    $this->actingAs($user)->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->where('ordersAvailable', false)
            ->where('customers.data.0.name', 'Walk-in')
            ->where('customers.data.0.orders_count', 0));
});
