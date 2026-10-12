<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Orders\Models\Order;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\EmployeeProfile;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function managerWith(Tenant $tenant, Branch $branch, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

function employeePayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Layla Haddad',
        'email' => 'layla-'.uniqid().'@example.com',
        'password' => 'Str0ng!Passw0rd#2026',
        'role' => 'Cashier',
        'is_active' => true,
        'phone' => '+961 70 123 456',
        'job_title' => 'Senior cashier',
        'hire_date' => '2024-03-01',
        'national_id' => 'ID-998877',
        'address' => 'Hamra, Beirut',
        'emergency_contact_name' => 'Karim Haddad',
        'emergency_contact_phone' => '+961 71 000 111',
        'notes' => 'Speaks Arabic and French.',
    ], $overrides);
}

beforeEach(function () {
    Role::findOrCreate('Cashier');

    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager = managerWith($this->tenant, $this->branch, ['view users', 'create users', 'edit users', 'delete users', 'view activity log']);

    $this->other = Tenant::factory()->create();
    $this->otherBranch = Branch::factory()->create(['tenant_id' => $this->other->id]);
    $this->otherManager = managerWith($this->other, $this->otherBranch, ['view users', 'create users', 'edit users', 'view activity log']);
});

describe('employee profiles', function () {
    it('stores profile details when creating an employee', function () {
        $this->actingAs($this->manager)
            ->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'layla@example.com']))
            ->assertRedirect(route('users.index'));

        $profile = User::where('email', 'layla@example.com')->first()->employeeProfile;

        expect($profile)->not->toBeNull()
            ->and($profile->tenant_id)->toBe($this->tenant->id)
            ->and($profile->phone)->toBe('+961 70 123 456')
            ->and($profile->job_title)->toBe('Senior cashier')
            ->and($profile->hire_date->toDateString())->toBe('2024-03-01')
            ->and($profile->emergency_contact_name)->toBe('Karim Haddad');
    });

    it('generates sequential employee numbers per tenant', function () {
        $this->actingAs($this->manager);

        $this->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id]));
        $this->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id]));
        $this->actingAs($this->otherManager)->post(route('users.store'), employeePayload(['branch_id' => $this->otherBranch->id]));

        $numbers = EmployeeProfile::where('tenant_id', $this->tenant->id)->orderBy('id')->pluck('employee_number')->all();
        $otherNumbers = EmployeeProfile::where('tenant_id', $this->other->id)->orderBy('id')->pluck('employee_number')->all();

        expect($numbers)->toContain('EMP-0001', 'EMP-0002')
            ->and($otherNumbers)->toContain('EMP-0001');
    });

    it('rejects a duplicate employee number inside a tenant but allows it in another', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'employee_number' => 'A-100']))->assertRedirect();

        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'employee_number' => 'A-100']))
            ->assertSessionHasErrors('employee_number');

        $this->actingAs($this->otherManager)->post(route('users.store'), employeePayload(['branch_id' => $this->otherBranch->id, 'employee_number' => 'A-100']))
            ->assertSessionHasNoErrors();
    });

    it('updates profile details and keeps the employee number when left unchanged', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'edit@example.com', 'employee_number' => 'E-7']));
        $employee = User::where('email', 'edit@example.com')->first();

        $this->actingAs($this->manager)->put(route('users.update', $employee), [
            'name' => 'Layla H.', 'email' => 'edit@example.com', 'branch_id' => $this->branch->id, 'role' => 'Cashier',
            'is_active' => true, 'employee_number' => 'E-7', 'job_title' => 'Shift lead', 'phone' => '999',
        ])->assertRedirect();

        $profile = $employee->fresh()->employeeProfile;
        expect($profile->job_title)->toBe('Shift lead')
            ->and($profile->phone)->toBe('999')
            ->and($profile->employee_number)->toBe('E-7');
    });

    it('creates a profile for an employee who does not have one yet when edited', function () {
        $employee = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);

        $this->actingAs($this->manager)->put(route('users.update', $employee), [
            'name' => $employee->name, 'email' => $employee->email, 'branch_id' => $this->branch->id, 'role' => 'Cashier',
            'is_active' => true, 'job_title' => 'Stock clerk',
        ])->assertRedirect();

        expect($employee->fresh()->employeeProfile)->toMatchArray(['job_title' => 'Stock clerk'])
            ->and($employee->fresh()->employeeProfile->employee_number)->toStartWith('EMP-');
    });

    it('validates profile fields', function (array $overrides, string $field) {
        $this->actingAs($this->manager)
            ->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id] + $overrides))
            ->assertSessionHasErrors($field);
    })->with([
        'bad hire date' => [['hire_date' => 'not-a-date'], 'hire_date'],
        'future birth date' => [['birth_date' => '2999-01-01'], 'birth_date'],
        'overlong phone' => [['phone' => str_repeat('1', 60)], 'phone'],
        'overlong job title' => [['job_title' => str_repeat('x', 101)], 'job_title'],
    ]);

    it('removes the profile with the employee', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'bye@example.com']));
        $employee = User::where('email', 'bye@example.com')->first();

        $this->actingAs($this->manager)->delete(route('users.destroy', $employee))->assertRedirect();

        expect(EmployeeProfile::where('user_id', $employee->id)->exists())->toBeFalse();
    });

    it('shows the employee profile page with details and recent activity', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'show@example.com']));
        $employee = User::where('email', 'show@example.com')->first();

        $this->actingAs($this->manager)->get(route('users.show', $employee))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('Users/Show')
                ->where('employee.name', 'Layla Haddad')
                ->where('employee.profile.job_title', 'Senior cashier')
                ->where('employee.role', 'Cashier')
                ->has('activity.data')
            );
    });

    it('keeps profiles of other tenants private', function () {
        $stranger = User::factory()->create(['tenant_id' => $this->other->id, 'branch_id' => $this->otherBranch->id]);

        $this->actingAs($this->manager)->get(route('users.show', $stranger))->assertForbidden();
    });

    it('requires the view permission for the profile page', function () {
        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);

        $this->actingAs($nobody)->get(route('users.show', $this->manager))->assertForbidden();
    });

    it('lists employees with profile data and searches by employee number or phone', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'employee_number' => 'Z-900', 'phone' => '5550001', 'name' => 'Needle']));
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'employee_number' => 'Z-901', 'phone' => '5559999', 'name' => 'Haystack']));

        $this->actingAs($this->manager)->get(route('users.index', ['search' => 'Z-900']))
            ->assertInertia(fn ($page) => $page
                ->has('users.data', 1)
                ->where('users.data.0.name', 'Needle')
                ->where('users.data.0.employee_profile.job_title', 'Senior cashier'));

        $this->actingAs($this->manager)->get(route('users.index', ['search' => '5559999']))
            ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.name', 'Haystack'));
    });
});

describe('activity log', function () {
    it('records sign-ins and sign-outs', function () {
        $user = User::factory()->create(['email' => 'log@example.com', 'password' => bcrypt('Str0ng!Passw0rd#2026'), 'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);

        $this->post('/login', ['email' => 'log@example.com', 'password' => 'Str0ng!Passw0rd#2026']);
        $this->post('/logout');

        $events = ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('event')->all();

        expect($events)->toBe(['auth.login', 'auth.logout']);
        expect(ActivityLog::where('event', 'auth.login')->first())->tenant_id->toBe($this->tenant->id);
    });

    it('records sales and voids with the cashier as the actor', function () {
        $location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 20]);
        StockMovement::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $product->id, 'location_id' => $location->id, 'quantity' => 5]);
        $cashier = managerWith($this->tenant, $this->branch, ['access pos', 'void orders', 'view orders']);

        $this->actingAs($cashier)->postJson(route('pos.checkout'), ['items' => [['product_id' => $product->id, 'quantity' => 1]], 'cash_received' => 50])->assertSuccessful();
        $order = Order::first();
        $this->actingAs($cashier)->post(route('orders.void', $order));

        $created = ActivityLog::where('event', 'order.created')->first();
        $voided = ActivityLog::where('event', 'order.voided')->first();

        expect($created)->user_id->toBe($cashier->id)->subject_id->toBe($order->id)
            ->and($created->description)->toContain($order->order_number)
            ->and($voided)->user_id->toBe($cashier->id);
    })->skip(fn () => ! moduleInstalled('sales') || ! moduleInstalled('catalog') || ! moduleInstalled('inventory'), 'Sales is not installed');

    it('records employee changes with the acting user', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'chg@example.com']));
        $employee = User::where('email', 'chg@example.com')->first();
        $this->actingAs($this->manager)->put(route('users.update', $employee), ['name' => 'Renamed', 'email' => 'chg@example.com', 'branch_id' => $this->branch->id, 'role' => 'Cashier', 'is_active' => true]);
        $this->actingAs($this->manager)->delete(route('users.destroy', $employee));

        $events = ActivityLog::where('user_id', $this->manager->id)->orderBy('id')->pluck('event')->all();

        expect($events)->toBe(['employee.created', 'employee.updated', 'employee.deleted']);
    });

    it('shows the activity page only to permitted users and only for their tenant', function () {
        ActivityLog::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->manager->id, 'event' => 'auth.login', 'description' => 'Mine']);
        ActivityLog::create(['tenant_id' => $this->other->id, 'user_id' => $this->otherManager->id, 'event' => 'auth.login', 'description' => 'Theirs']);

        $this->actingAs($this->manager)->get(route('activity.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('Activity/Index')->has('logs.data', 1)->where('logs.data.0.description', 'Mine'));

        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
        $this->actingAs($nobody)->get(route('activity.index'))->assertForbidden();
    });

    it('filters the activity page by employee, event, date and text', function () {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
        $old = ActivityLog::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->manager->id, 'event' => 'order.created', 'description' => 'Sale ORD-1']);
        $old->forceFill(['created_at' => '2026-01-10 10:00:00'])->save();
        ActivityLog::create(['tenant_id' => $this->tenant->id, 'user_id' => $other->id, 'event' => 'order.voided', 'description' => 'Void ORD-2']);
        ActivityLog::create(['tenant_id' => $this->tenant->id, 'user_id' => $this->manager->id, 'event' => 'auth.login', 'description' => 'Signed in']);

        $this->actingAs($this->manager);

        $this->get(route('activity.index', ['user_id' => $other->id]))->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.event', 'order.voided'));
        $this->get(route('activity.index', ['event' => 'order.created']))->assertInertia(fn ($page) => $page->has('logs.data', 1));
        $this->get(route('activity.index', ['date_to' => '2026-01-31']))->assertInertia(fn ($page) => $page->has('logs.data', 1)->where('logs.data.0.event', 'order.created'));
        $this->get(route('activity.index', ['search' => 'ORD-2']))->assertInertia(fn ($page) => $page->has('logs.data', 1));
    });

    it('limits the profile activity to that employee', function () {
        $this->actingAs($this->manager)->post(route('users.store'), employeePayload(['branch_id' => $this->branch->id, 'email' => 'only@example.com']));
        $employee = User::where('email', 'only@example.com')->first();
        ActivityLog::create(['tenant_id' => $this->tenant->id, 'user_id' => $employee->id, 'event' => 'auth.login', 'description' => 'Signed in']);

        $this->actingAs($this->manager)->get(route('users.show', $employee))
            ->assertInertia(fn ($page) => $page->has('activity.data', 1)->where('activity.data.0.user_id', $employee->id));
    });
});
