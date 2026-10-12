<?php

use App\Modules\Company\Actions\CreateCompanyAction;
use App\Modules\Company\Models\Location;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Team\Models\User;

it('creates a company with its main branch, location, owner and subscription', function () {
    $plan = Plan::factory()->create();

    $company = app(CreateCompanyAction::class)->execute([
        'name' => 'Beirut Mart', 'currency' => 'USD', 'plan_id' => $plan->id,
        'admin_name' => 'Rana', 'admin_email' => 'rana@example.com', 'admin_password' => 'Str0ng!Passw0rd#2026',
    ]);

    $owner = User::where('email', 'rana@example.com')->firstOrFail();

    expect($company->name)->toBe('Beirut Mart')
        ->and((float) $company->tax_rate)->toBe(0.0)
        ->and($company->is_active)->toBeTrue()
        ->and($company->branches()->pluck('name')->all())->toBe(['Main Branch'])
        ->and(Location::where('company_id', $company->id)->where('branch_id', $company->branches()->first()->id)->exists())->toBeTrue()
        ->and($owner->company_id)->toBe($company->id)
        ->and($owner->branch_id)->toBe($company->branches()->first()->id)
        ->and($owner->hasRole('Owner'))->toBeTrue()
        ->and($company->subscriptions()->where('plan_id', $plan->id)->exists())->toBeTrue();
});

it('creates nothing when the plan does not exist', function () {
    try {
        app(CreateCompanyAction::class)->execute([
            'name' => 'Ghost', 'currency' => 'USD', 'plan_id' => 999999,
            'admin_name' => 'Nobody', 'admin_email' => 'nobody@example.com', 'admin_password' => 'Str0ng!Passw0rd#2026',
        ]);
    } catch (Throwable) {
        // The plan lookup fails after the rest was written, so everything must roll back.
    }

    expect(User::where('email', 'nobody@example.com')->exists())->toBeFalse();
});
