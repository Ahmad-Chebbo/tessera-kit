<?php

use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id,
        'email' => 'rana@example.com', 'password' => Hash::make('Str0ng!Passw0rd#2026'),
    ])->givePermissionTo(Permission::findOrCreate('view expenses'));
});

it('exchanges credentials for a token that opens the API', function () {
    $response = $this->postJson('/api/v1/auth/token', ['email' => 'rana@example.com', 'password' => 'Str0ng!Passw0rd#2026', 'device_name' => 'phone'])
        ->assertCreated()
        ->assertJsonPath('user.email', 'rana@example.com')
        ->assertJsonPath('user.tenant_id', $this->tenant->id);

    $this->withToken($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'rana@example.com')->assertJsonPath('data.permissions.0', 'view expenses');
});

it('refuses wrong passwords, unknown emails and deactivated accounts alike', function (array $overrides) {
    $this->user->update(['is_active' => $overrides['active'] ?? true]);

    $this->postJson('/api/v1/auth/token', [
        'email' => $overrides['email'] ?? 'rana@example.com',
        'password' => $overrides['password'] ?? 'Str0ng!Passw0rd#2026',
        'device_name' => 'phone',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email'])->assertJsonMissing(['token']);
})->with([
    'wrong password' => [['password' => 'nope']],
    'unknown email' => [['email' => 'ghost@example.com']],
    'deactivated' => [['active' => false]],
]);

it('validates the request', function () {
    $this->postJson('/api/v1/auth/token', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password', 'device_name']);
});

it('revokes only the token that asked', function () {
    $token = fn (string $device) => $this->postJson('/api/v1/auth/token', ['email' => 'rana@example.com', 'password' => 'Str0ng!Passw0rd#2026', 'device_name' => $device])->json('token');
    $phone = $token('phone');
    $laptop = $token('laptop');

    $this->withToken($phone)->deleteJson('/api/v1/auth/token')->assertNoContent();
    expect($this->user->tokens()->pluck('name')->all())->toBe(['laptop']);

    $this->app['auth']->forgetGuards();
    $this->withToken($phone)->getJson('/api/v1/me')->assertUnauthorized();

    $this->app['auth']->forgetGuards();
    $this->withToken($laptop)->getJson('/api/v1/me')->assertOk();
});

it('shuts out deactivated users and unusable subscriptions even with a token', function () {
    $token = $this->postJson('/api/v1/auth/token', ['email' => 'rana@example.com', 'password' => 'Str0ng!Passw0rd#2026', 'device_name' => 'phone'])->json('token');

    $this->user->update(['is_active' => false]);
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();
});
