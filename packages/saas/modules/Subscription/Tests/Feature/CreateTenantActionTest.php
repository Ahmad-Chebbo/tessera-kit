<?php

use App\Modules\Subscription\Actions\CreateTenantAction;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Location;

it('creates a tenant with its main branch, location, owner and subscription', function () {
    $plan = Plan::factory()->create();

    $tenant = app(CreateTenantAction::class)->execute([
        'name' => 'Beirut Mart', 'currency' => 'USD', 'plan_id' => $plan->id,
        'admin_name' => 'Rana', 'admin_email' => 'rana@example.com', 'admin_password' => 'Str0ng!Passw0rd#2026',
    ]);

    $owner = User::where('email', 'rana@example.com')->firstOrFail();

    expect($tenant->name)->toBe('Beirut Mart')
        ->and((float) $tenant->tax_rate)->toBe(0.0)
        ->and($tenant->is_active)->toBeTrue()
        ->and($tenant->branches()->pluck('name')->all())->toBe(['Main Branch'])
        ->and(Location::where('tenant_id', $tenant->id)->where('branch_id', $tenant->branches()->first()->id)->exists())->toBeTrue()
        ->and($owner->tenant_id)->toBe($tenant->id)
        ->and($owner->branch_id)->toBe($tenant->branches()->first()->id)
        ->and($owner->hasRole('Owner'))->toBeTrue()
        ->and($tenant->subscriptions()->where('plan_id', $plan->id)->exists())->toBeTrue();
});

it('creates nothing when the plan does not exist', function () {
    try {
        app(CreateTenantAction::class)->execute([
            'name' => 'Ghost', 'currency' => 'USD', 'plan_id' => 999999,
            'admin_name' => 'Nobody', 'admin_email' => 'nobody@example.com', 'admin_password' => 'Str0ng!Passw0rd#2026',
        ]);
    } catch (Throwable) {
        // The plan lookup fails after the rest was written, so everything must roll back.
    }

    expect(User::where('email', 'nobody@example.com')->exists())->toBeFalse();
});
