<?php

use App\Modules\Team\Models\User;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tessera\Engine\ModuleRegistry;

it('creates the permissions, the roles and an owner who can do everything', function () {
    $this->artisan('tessera:install', ['--name' => 'Sara', '--email' => 'sara@example.com', '--password' => 'a-strong-secret', '--no-interaction' => true])
        ->assertSuccessful();

    $user = User::query()->where('email', 'sara@example.com')->firstOrFail();

    expect($user->name)->toBe('Sara')
        ->and(Hash::check('a-strong-secret', $user->password))->toBeTrue()
        ->and($user->hasRole('Owner'))->toBeTrue()
        ->and($user->getAllPermissions()->count())->toBe(Permission::count())
        ->and(Permission::where('name', 'view users')->exists())->toBeTrue();
})->skip(fn () => app(ModuleRegistry::class)->has('subscription'), 'The platform admin is created in a SaaS project.');

it('makes a random password when none is given and the command is not interactive', function () {
    $this->artisan('tessera:install', ['--email' => 'sara@example.com', '--no-interaction' => true])
        ->expectsOutputToContain('Password:')
        ->assertSuccessful();

    expect(User::query()->where('email', 'sara@example.com')->exists())->toBeTrue();
});

it('needs an email when it cannot ask for one', function () {
    $this->artisan('tessera:install', ['--no-interaction' => true])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('gives the role to an existing user and keeps their password', function () {
    $user = User::factory()->create(['email' => 'sara@example.com', 'password' => 'old-secret']);
    $hash = $user->password;

    $this->artisan('tessera:install', ['--email' => 'sara@example.com', '--password' => 'new-secret', '--no-interaction' => true])->assertSuccessful();

    expect($user->fresh()->password)->toBe($hash)
        ->and($user->fresh()->roles()->count())->toBe(1);
});

it('can be run twice without making a second account', function () {
    foreach (range(1, 2) as $ignored) {
        $this->artisan('tessera:install', ['--email' => 'sara@example.com', '--no-interaction' => true])->assertSuccessful();
    }

    expect(User::query()->count())->toBe(1)
        ->and(Role::query()->where('name', 'Owner')->count())->toBe(1);
});

it('leaves the permissions alone after migrating while testing', function () {
    event(new MigrationsEnded('up'));

    expect(Permission::count())->toBe(0);
});

it('makes the first account the platform admin when tenants are served', function () {
    $this->artisan('tessera:install', ['--email' => 'sara@example.com', '--no-interaction' => true])->assertSuccessful();

    $user = User::query()->where('email', 'sara@example.com')->firstOrFail();

    expect($user->hasRole('Admin'))->toBeTrue()
        ->and($user->tenant_id)->toBeNull();
})->skip(fn () => ! app(ModuleRegistry::class)->has('subscription'), 'Only a project with the Subscription module has a platform admin.');
