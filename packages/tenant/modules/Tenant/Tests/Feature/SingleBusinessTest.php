<?php

use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantAccess;
use Tessera\Core\Contracts\Tenancy;
use Tessera\Engine\ModuleRegistry;

/**
 * A project with one business: no Subscription module, so the first tenant is the only one and everyone belongs to it.
 */
function behaveAsOneBusiness(): void
{
    app()->singleton(TenantAccess::class, fn () => new class(app(ModuleRegistry::class)) extends TenantAccess
    {
        public function isMultiTenant(): bool
        {
            return false;
        }
    });
    app()->bind(Tenancy::class, TenantAccess::class);
}

it('makes the default tenant once, with a main branch and its stock location', function () {
    Tenant::query()->delete();

    $tenant = Tenant::default();

    expect(Tenant::count())->toBe(1)
        ->and(Tenant::default()->is($tenant))->toBeTrue()
        ->and(Branch::query()->where('tenant_id', $tenant->id)->pluck('name')->all())->toBe(['Main Branch'])
        ->and(Location::query()->where('tenant_id', $tenant->id)->where('branch_id', $tenant->mainBranch()->id)->count())->toBe(1);
});

it('puts every new user in the default tenant and its main branch', function () {
    Tenant::query()->delete();
    behaveAsOneBusiness();

    $user = User::factory()->create();

    expect($user->tenant_id)->toBe(Tenant::default()->id)
        ->and($user->branch_id)->toBe(Tenant::default()->mainBranch()->id)
        ->and(Tenant::count())->toBe(1);

    $other = User::factory()->create();

    expect($other->tenant_id)->toBe($user->tenant_id);
});

it('leaves a user who already has a tenant where they are', function () {
    behaveAsOneBusiness();

    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    expect($user->tenant_id)->toBe($tenant->id);
});

it('shares the roles in one business and gives each tenant its own when there are many', function () {
    $user = User::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

    expect(app(TenantAccess::class)->isMultiTenant())->toBeTrue()
        ->and(app(TenantAccess::class)->roleOwner($user))->toBe($user->tenant_id);

    behaveAsOneBusiness();

    expect(app(Tenancy::class)->roleOwner($user))->toBeNull();
});
