<?php

use App\Modules\Subscription\Actions\ConvertToSaasAction;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use App\Modules\Tenant\Models\Tenant;

/**
 * A project that ran as one business and now gets the Subscription module: the business becomes the first tenant. The
 * shape of that project is rebuilt by hand, with roles nobody owns, users in the one tenant and no subscription.
 */
beforeEach(function () {
    // The project has been running for a while: its permissions and default roles exist.
    app(PermissionSynchronizer::class)->sync();

    $this->tenant = Tenant::factory()->create(['name' => 'The shop']);
    Subscription::query()->delete();
    Plan::query()->updateOrCreate(['slug' => 'legacy'], ['name' => 'Legacy (all modules)', 'price' => 0, 'billing_interval' => Plan::LIFETIME, 'modules' => ['catalog'], 'is_active' => false]);

    $this->owner = Role::query()->firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'tenant_id' => null]);
    $this->owner->syncPermissions(['view users', 'manage roles']);
    Role::query()->firstOrCreate(['name' => 'Manager', 'guard_name' => 'web', 'tenant_id' => null])->syncPermissions(['view users']);
    $this->support = Role::query()->create(['name' => 'Support', 'guard_name' => 'web', 'tenant_id' => null]);
    $this->support->syncPermissions(['view users']);

    $this->boss = User::factory()->create(['tenant_id' => $this->tenant->id])->assignRole($this->owner);
    $this->helper = User::factory()->create(['tenant_id' => $this->tenant->id])->assignRole($this->support);
});

it('gives the business its own roles and moves its users onto them', function () {
    $report = app(ConvertToSaasAction::class)->execute();

    $ownerCopy = Role::query()->where('name', 'Owner')->where('tenant_id', $this->tenant->id)->first();

    expect($ownerCopy)->not->toBeNull()
        ->and($ownerCopy->permissions->pluck('name')->sort()->values()->all())->toBe(['manage roles', 'view users'])
        ->and($this->boss->fresh()->roles->pluck('id')->all())->toBe([$ownerCopy->id])
        ->and(Role::query()->whereNull('tenant_id')->where('name', 'Owner')->exists())->toBeTrue()
        ->and($report['tenants'][0]['roles_moved'])->toBe(1);
});

it('makes the roles a single business created for itself the tenant\'s own', function () {
    app(ConvertToSaasAction::class)->execute();

    expect($this->support->fresh()->tenant_id)->toBe($this->tenant->id)
        ->and($this->helper->fresh()->hasRole('Support'))->toBeTrue();
});

it('subscribes the business to a plan with every installed module', function () {
    $report = app(ConvertToSaasAction::class)->execute();

    $subscription = $this->tenant->fresh()->subscription;

    expect($subscription)->not->toBeNull()
        ->and($subscription->plan->slug)->toBe('legacy')
        ->and($subscription->plan->modules)->toBe(Module::values())
        ->and($report['plan_modules'])->toBe(Module::values())
        ->and($report['tenants'][0]['subscribed'])->toBeTrue();
});

it('changes nothing the second time', function () {
    app(ConvertToSaasAction::class)->execute();
    $roles = Role::query()->count();
    $subscriptions = Subscription::query()->count();

    $report = app(ConvertToSaasAction::class)->execute();

    expect(Role::query()->count())->toBe($roles)
        ->and(Subscription::query()->count())->toBe($subscriptions)
        ->and($report['tenants'][0]['roles_moved'])->toBe(0)
        ->and($report['tenants'][0]['subscribed'])->toBeFalse();
});

it('creates the platform admin outside every tenant', function () {
    $report = app(ConvertToSaasAction::class)->execute('boss@platform.test', 'Platform boss', 'a-long-password-1');

    $admin = User::query()->where('email', 'boss@platform.test')->firstOrFail();

    expect($report['admin'])->toBe(['email' => 'boss@platform.test', 'created' => true, 'password' => 'a-long-password-1'])
        ->and($admin->tenant_id)->toBeNull()
        ->and($admin->hasRole('Admin'))->toBeTrue()
        ->and($admin->can('manage tenants'))->toBeTrue();
});

it('makes a password when none is given and does not touch the password of an existing admin', function () {
    $generated = app(ConvertToSaasAction::class)->execute('new@platform.test')['admin'];

    expect(strlen($generated['password']))->toBe(16);

    $existing = User::factory()->create(['tenant_id' => null, 'email' => 'old@platform.test']);
    $hash = $existing->password;

    $report = app(ConvertToSaasAction::class)->execute('old@platform.test')['admin'];

    expect($report['created'])->toBeFalse()
        ->and($report['password'])->toBeNull()
        ->and($existing->fresh()->password)->toBe($hash)
        ->and($existing->fresh()->hasRole('Admin'))->toBeTrue();
});

it('refuses to make someone who belongs to a tenant the platform admin', function () {
    expect(fn () => app(ConvertToSaasAction::class)->execute($this->boss->email))->toThrow(InvalidArgumentException::class, 'belongs to a tenant');

    expect($this->boss->fresh()->hasRole('Admin'))->toBeFalse();
});

it('is available as an artisan command', function () {
    $this->artisan('saas:install', ['--admin-email' => 'cli@platform.test', '--admin-password' => 'a-long-password-2'])
        ->expectsOutputToContain('cli@platform.test')
        ->assertSuccessful();

    $this->artisan('saas:install', ['--admin-email' => $this->boss->email])->assertFailed();
});
