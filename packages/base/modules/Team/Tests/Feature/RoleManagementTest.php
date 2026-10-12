<?php

use App\Modules\Subscription\Services\RoleTemplates;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function ownerOf(Tenant $tenant, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
    $role = Role::create(['name' => 'Owner', 'guard_name' => 'web', 'tenant_id' => $tenant->id]);
    $role->syncPermissions($permissions);

    return tap(User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]))->assignRole($role);
}

beforeEach(function () {
    foreach (['view products', 'edit products', 'manage tenants'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->tenant = Tenant::factory()->create();
    $this->other = Tenant::factory()->create();
    $this->owner = ownerOf($this->tenant, ['manage roles', 'view users', 'create users', 'view products', 'edit products']);
    $this->otherOwner = ownerOf($this->other, ['manage roles', 'view products']);
});

it('creates a tenant role with permissions and logs it', function () {
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Supervisor', 'permissions' => ['view products']])
        ->assertRedirect(route('roles.index'));

    $role = Role::where('name', 'Supervisor')->firstOrFail();
    expect($role->tenant_id)->toBe($this->tenant->id)
        ->and($role->permissions->pluck('name')->all())->toBe(['view products'])
        ->and(ActivityLog::where('event', 'role.created')->count())->toBe(1);
});

it('allows the same role name in different tenants but not twice in one', function () {
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Supervisor', 'permissions' => []])->assertSessionHasNoErrors();
    $this->actingAs($this->otherOwner)->post(route('roles.store'), ['name' => 'Supervisor', 'permissions' => []])->assertSessionHasNoErrors();
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Supervisor', 'permissions' => []])->assertSessionHasErrors('name');
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Admin', 'permissions' => []])->assertSessionHasErrors('name');

    expect(Role::where('name', 'Supervisor')->count())->toBe(2);
});

it('only lets a user grant permissions they hold, never platform ones', function () {
    $this->actingAs($this->otherOwner)->post(route('roles.store'), ['name' => 'Sneaky', 'permissions' => ['edit products']])
        ->assertSessionHasErrors('permissions.0');
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Sneaky', 'permissions' => ['manage tenants']])
        ->assertSessionHasErrors('permissions.0');
    expect(Role::where('name', 'Sneaky')->exists())->toBeFalse();
});

it('updates a role and keeps permissions the editor cannot grant', function () {
    $role = Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $role->syncPermissions(['view products', 'manage tenants']);

    $this->actingAs($this->owner)->put(route('roles.update', $role), ['name' => 'Senior clerk', 'permissions' => ['edit products']])
        ->assertRedirect(route('roles.index'));

    $role->refresh();
    expect($role->name)->toBe('Senior clerk')
        ->and($role->permissions->pluck('name')->sort()->values()->all())->toBe(['edit products', 'manage tenants']);
});

it('stops an owner removing role management from their own role', function () {
    $own = $this->owner->roles->first();

    $this->actingAs($this->owner)->put(route('roles.update', $own), ['name' => 'Owner', 'permissions' => ['view products']])
        ->assertSessionHasErrors('permissions');
});

it('refuses to delete a role that still has employees and deletes an empty one', function () {
    $role = Role::create(['name' => 'Clerk', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $worker = User::factory()->create(['tenant_id' => $this->tenant->id])->assignRole($role);

    $this->actingAs($this->owner)->delete(route('roles.destroy', $role))->assertSessionHas('error');
    expect(Role::find($role->id))->not->toBeNull();

    $worker->removeRole($role);
    $this->actingAs($this->owner)->delete(route('roles.destroy', $role))->assertSessionHas('success');
    expect(Role::find($role->id))->toBeNull()->and(ActivityLog::where('event', 'role.deleted')->count())->toBe(1);
});

it('keeps roles private to their tenant', function () {
    $theirs = Role::where('tenant_id', $this->other->id)->firstOrFail();

    $this->actingAs($this->owner)->get(route('roles.edit', $theirs))->assertForbidden();
    $this->actingAs($this->owner)->put(route('roles.update', $theirs), ['name' => 'Hacked', 'permissions' => []])->assertForbidden();
    $this->actingAs($this->owner)->delete(route('roles.destroy', $theirs))->assertForbidden();

    $this->actingAs($this->owner)->get(route('roles.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Roles/Index')->has('roles', 1)->where('roles.0.is_mine', true));
});

it('requires the manage roles permission', function () {
    $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($user)->post(route('roles.store'), ['name' => 'X', 'permissions' => []])->assertForbidden();
});

it('shows the editor permission groups with what the user can grant', function () {
    $this->actingAs($this->otherOwner)->get(route('roles.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Roles/Edit')
            ->where('role', null)
            ->where('groups', fn ($groups) => collect($groups)->flatMap(fn ($group) => $group['permissions'])->contains(fn ($permission) => $permission['name'] === 'edit products' && $permission['grantable'] === false)));
});

it('assigns the role of the employee tenant when creating employees', function () {
    $mine = Role::create(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
    $theirs = Role::create(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->other->id]);

    $this->actingAs($this->owner)->post(route('users.store'), [
        'name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'Str0ng!Passw0rd#2026',
        'branch_id' => $this->owner->branch_id, 'role' => 'Cashier', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $sam = User::where('email', 'sam@example.com')->firstOrFail();
    expect($sam->roles->pluck('id')->all())->toBe([$mine->id])->and($sam->roles->contains('id', $theirs->id))->toBeFalse();
});

it('lists only the tenant roles when assigning employees', function () {
    Role::create(['name' => 'Cashier', 'guard_name' => 'web', 'tenant_id' => $this->other->id]);

    $this->actingAs($this->owner)->get(route('users.create'))
        ->assertInertia(fn (Assert $page) => $page->has('roles', 1)->where('roles.0.name', 'Owner'));
});

it('provisions a tenant with its own copy of the standard roles', function () {
    $template = Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
    $template->syncPermissions(['view products']);

    $roles = app(RoleTemplates::class)->provision(Tenant::factory()->create());

    expect($roles->keys()->all())->toBe(RoleTemplates::NAMES)
        ->and($roles['Cashier']->tenant_id)->not->toBeNull()
        ->and($roles['Cashier']->permissions->pluck('name')->all())->toBe(['view products'])
        ->and($roles['Owner']->id)->not->toBe($template->id);

    $template->syncPermissions([]);
    expect($roles['Cashier']->fresh()->permissions)->toHaveCount(1);
});
