<?php

use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use App\Modules\Team\Services\TeamFeatures;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tessera\Core\Contracts\BranchScope;
use Tessera\Core\Contracts\Defaults\NoBranches;
use Tessera\Core\Contracts\Defaults\NoPlanLimits;
use Tessera\Core\Contracts\Defaults\NoTenancy;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Core\Contracts\Tenancy;

/**
 * What the fresh starter runs: no Tenant module, so no tenant column, no branches, no plan limits and roles everyone shares.
 */
beforeEach(function () {
    app()->bind(Tenancy::class, NoTenancy::class);
    app()->bind(BranchScope::class, NoBranches::class);
    app()->bind(PlanLimits::class, NoPlanLimits::class);
    config(['team.employee_profiles' => false, 'team.activity_log' => false]);

    foreach (['view users', 'create users', 'edit users', 'delete users', 'manage roles'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->owner = User::factory()->create(['tenant_id' => null, 'branch_id' => null])
        ->givePermissionTo(['view users', 'create users', 'edit users', 'delete users', 'manage roles']);
    Role::query()->firstOrCreate(['name' => 'Manager', 'guard_name' => 'web', 'tenant_id' => null]);
});

it('tells the pages which optional parts are off', function () {
    $this->actingAs($this->owner)->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Users/Index')->where('features', ['branches' => false, 'employeeProfiles' => false, 'activityLog' => false]));
});

it('creates, updates and deletes a user without a branch or a profile', function () {
    $statements = [];
    DB::listen(function ($query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    $this->actingAs($this->owner)->post(route('users.store'), [
        'name' => 'Sam', 'email' => 'sam@example.com', 'password' => 'Str0ng!Passw0rd#2026', 'role' => 'Manager', 'is_active' => true,
    ])->assertRedirect(route('users.index'));

    $sam = User::where('email', 'sam@example.com')->firstOrFail();

    expect($sam->tenant_id)->toBeNull()
        ->and($sam->branch_id)->toBeNull()
        ->and($sam->hasRole('Manager'))->toBeTrue()
        ->and($sam->employeeProfile)->toBeNull();

    $this->actingAs($this->owner)->put(route('users.update', $sam), [
        'name' => 'Sam B', 'email' => 'sam@example.com', 'role' => 'Manager', 'is_active' => true,
    ])->assertRedirect(route('users.index'));

    expect($sam->fresh()->name)->toBe('Sam B');

    $this->actingAs($this->owner)->get(route('users.show', $sam))->assertOk();
    $this->actingAs($this->owner)->get(route('users.edit', $sam))->assertOk();
    $this->actingAs($this->owner)->delete(route('users.destroy', $sam))->assertRedirect(route('users.index'));

    expect(User::find($sam->id))->toBeNull();

    $writes = collect($statements)->filter(fn (string $sql): bool => str_starts_with($sql, 'insert') || str_starts_with($sql, 'update'));

    expect($writes->filter(fn (string $sql): bool => str_contains($sql, 'tenant_id') || str_contains($sql, 'branch_id')))->toBeEmpty();
});

it('manages the shared roles when there are no tenants', function () {
    $this->actingAs($this->owner)->post(route('roles.store'), ['name' => 'Support', 'permissions' => ['view users']])
        ->assertRedirect(route('roles.index'));

    $role = Role::query()->where('name', 'Support')->firstOrFail();

    expect($role->tenant_id)->toBeNull()
        ->and($role->permissions->pluck('name')->all())->toBe(['view users']);

    $this->actingAs($this->owner)->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('roles', 2));
    $this->actingAs($this->owner)->get(route('roles.edit', $role))->assertOk();
});

it('records the activity log only when it is on', function () {
    expect(app(ActivityLogger::class)->record(ActivityEvent::Login, 'Signed in', null, [], $this->owner))->toBeNull()
        ->and(ActivityLog::count())->toBe(0);

    config(['team.activity_log' => true]);

    expect(app(ActivityLogger::class)->record(ActivityEvent::Login, 'Signed in', null, [], $this->owner))->toBeInstanceOf(ActivityLog::class)
        ->and(ActivityLog::count())->toBe(1);
});

it('follows the Tenant module for the optional parts unless the config decides', function () {
    config(['team.employee_profiles' => null, 'team.activity_log' => null]);

    expect(app(TeamFeatures::class)->employeeProfiles())->toBeTrue()
        ->and(app(TeamFeatures::class)->activityLog())->toBeTrue();

    config(['team.employee_profiles' => false]);

    expect(app(TeamFeatures::class)->employeeProfiles())->toBeFalse()
        ->and(app(TeamFeatures::class)->activityLog())->toBeTrue();
});
