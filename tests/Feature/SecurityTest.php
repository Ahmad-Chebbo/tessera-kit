<?php

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Team\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function grantSecurity(User $user, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return $user->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->company = Company::factory()->create(['currency' => 'USD']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->cashier = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
});

describe('user roles', function () {
    beforeEach(function () {
        Role::findOrCreate('Admin');
        Role::findOrCreate('Owner');
        Role::findOrCreate('Cashier');
        $this->owner = grantSecurity(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]), ['create users', 'edit users']);
        $this->owner->assignRole('Owner');
    });

    $newUser = fn ($test, string $role) => [
        'name' => 'New', 'email' => 'new-'.uniqid().'@example.com', 'password' => 'Str0ng!Passw0rd#2026',
        'branch_id' => $test->branch->id, 'role' => $role,
    ];

    it('does not let a company user hand out the cross-company Admin role', function () use ($newUser) {
        $this->actingAs($this->owner)->post(route('users.store'), $newUser($this, 'Admin'))->assertSessionHasErrors('role');

        expect(User::role('Admin')->count())->toBe(0);
    });

    it('does not let a company user promote an existing user to Admin', function () {
        $target = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);

        $this->actingAs($this->owner)->put(route('users.update', $target), [
            'name' => $target->name, 'email' => $target->email, 'branch_id' => $this->branch->id, 'role' => 'Admin',
        ])->assertSessionHasErrors('role');

        expect($target->fresh()->hasRole('Admin'))->toBeFalse();
    });

    it('still lets them assign ordinary roles', function () use ($newUser) {
        $this->actingAs($this->owner)->post(route('users.store'), $newUser($this, 'Cashier'))->assertRedirect(route('users.index'));
    });

    it('lets a platform Admin assign the Admin role', function () use ($newUser) {
        $admin = grantSecurity(User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]), ['create users']);
        $admin->assignRole('Admin');

        $this->actingAs($admin)->post(route('users.store'), $newUser($this, 'Admin'))->assertRedirect(route('users.index'));
    });

    it('does not offer the Admin role to company users in the form', function () {
        $this->actingAs($this->owner)->get(route('users.create'))
            ->assertInertia(fn ($page) => $page->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain('Admin')));
    });
});

describe('accounts and sessions', function () {
    it('blocks deactivated users from logging in', function () {
        $user = User::factory()->create(['email' => 'gone@example.com', 'password' => bcrypt('Str0ng!Passw0rd#2026'), 'is_active' => false, 'company_id' => $this->company->id, 'branch_id' => $this->branch->id]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Str0ng!Passw0rd#2026'])->assertSessionHasErrors('email');
        $this->assertGuest();
    });

    it('signs out a user who is deactivated mid-session', function () {
        $this->actingAs($this->cashier)->get(route('profile.edit'))->assertSuccessful();

        $this->cashier->update(['is_active' => false]);

        $this->actingAs($this->cashier->fresh())->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    });
});

describe('transport hardening', function () {
    it('sends basic security headers', function () {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    });

    it('does not record requests with Telescope unless explicitly enabled outside local', function () {
        $default = file_get_contents(base_path('config/telescope.php'));

        expect($default)->not->toContain("env('TELESCOPE_ENABLED', true)");
    });
});
