<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\Entitlements;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function planFor(Tenant $tenant, array $modules, array $attributes = []): Subscription
{
    $plan = Plan::factory()->withModules($modules)->create($attributes);
    $tenant->subscriptions()->delete();

    return Subscription::create(['tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active, 'starts_at' => now()->subDay()]);
}

function staff(Tenant $tenant, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    $branch = Branch::first() ?? Branch::factory()->create(['tenant_id' => $tenant->id]);

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => Branch::where('tenant_id', $tenant->id)->value('id') ?? $branch->id])->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    Branch::factory()->create(['tenant_id' => $this->tenant->id]);

    Permission::findOrCreate('manage tenants');
    $admin = Role::create(['name' => 'Admin', 'guard_name' => 'web']);
    $admin->givePermissionTo('manage tenants');
    $this->admin = User::factory()->create(['tenant_id' => null])->assignRole($admin);
});

describe('module gating', function () {
    it('denies permissions of modules the plan leaves out', function () {
        planFor($this->tenant, [Module::Catalog]);
        $user = staff($this->tenant, ['view products', 'view customers']);

        expect($user->can('view products'))->toBeTrue()
            ->and($user->can('view customers'))->toBeFalse();
    });

    it('blocks routes of disabled modules and allows enabled ones', function () {
        planFor($this->tenant, [Module::Customers]);
        $user = staff($this->tenant, ['view customers', 'view products']);

        $this->actingAs($user)->get(route('customers.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertForbidden();
    });

    it('guards permissionless module routes with the module middleware', function () {
        planFor($this->tenant, [Module::Catalog]);
        $user = staff($this->tenant, []);

        $this->actingAs($user)->get(route('attendance.index'))->assertRedirect(route('subscription.show'));
        $this->actingAs($user)->get(route('pos-shifts.index'))->assertRedirect(route('subscription.show'));
        $this->actingAs($user)->getJson(route('pos-shifts.index'))->assertForbidden();

        planFor($this->tenant, [Module::Attendance]);
        $this->actingAs($user)->get(route('attendance.index'))->assertOk();
    });

    it('applies per-tenant overrides on top of the plan', function () {
        $subscription = planFor($this->tenant, [Module::Catalog, Module::Customers]);
        $subscription->update(['module_overrides' => ['customers' => false, 'expenses' => true]]);
        $user = staff($this->tenant, ['view customers', 'view expenses', 'view products']);

        expect($user->can('view customers'))->toBeFalse()
            ->and($user->can('view expenses'))->toBeTrue()
            ->and($user->can('view products'))->toBeTrue();
    });

    it('leaves core permissions and platform admins alone', function () {
        planFor($this->tenant, []);
        $user = staff($this->tenant, ['view users', 'manage settings']);

        expect($user->can('view users'))->toBeTrue()->and($user->can('manage settings'))->toBeTrue()
            ->and($this->admin->can('manage tenants'))->toBeTrue();
    });

    it('hides disabled modules from the shared permissions and lists the plan', function () {
        planFor($this->tenant, [Module::Catalog], ['name' => 'Tiny']);
        $user = staff($this->tenant, ['view products', 'view customers']);

        $this->actingAs($user)->get(route('subscription.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', ['view products'])
                ->where('plan.name', 'Tiny')
                ->where('plan.modules', ['catalog']));
    });

    it('picks up plan changes immediately', function () {
        $subscription = planFor($this->tenant, [Module::Catalog]);
        $user = staff($this->tenant, ['view customers']);
        expect($user->can('view customers'))->toBeFalse();

        $subscription->plan->update(['modules' => ['catalog', 'customers']]);

        expect($user->can('view customers'))->toBeTrue();
    });
});

describe('subscription state', function () {
    it('sends users of an expired subscription to the expired page', function () {
        $subscription = planFor($this->tenant, Module::cases());
        $subscription->update(['ends_at' => now()->subDay()]);
        $user = staff($this->tenant, ['view products']);

        $this->actingAs($user)->get(route('products.index'))->assertRedirect(route('subscription.expired'));
        $this->actingAs($user)->get(route('subscription.expired'))->assertOk();
        $this->actingAs($user)->getJson(route('products.index'))->assertForbidden();
        expect($user->can('view products'))->toBeFalse();
    });

    it('treats trials, canceled-until-end and past due correctly', function () {
        $subscription = planFor($this->tenant, Module::cases());

        $subscription->update(['status' => SubscriptionStatus::Trialing, 'trial_ends_at' => now()->addDays(3)]);
        expect($subscription->fresh()->isUsable())->toBeTrue();

        $subscription->update(['trial_ends_at' => now()->subMinute()]);
        expect($subscription->fresh()->isUsable())->toBeFalse()->and($subscription->fresh()->effectiveStatus())->toBe(SubscriptionStatus::Expired);

        $subscription->update(['status' => SubscriptionStatus::Canceled, 'ends_at' => now()->addWeek()]);
        expect($subscription->fresh()->isUsable())->toBeTrue();

        $subscription->update(['status' => SubscriptionStatus::PastDue, 'ends_at' => null]);
        expect($subscription->fresh()->isUsable())->toBeFalse();
    });

    it('gives a tenant without a subscription no modules', function () {
        $this->tenant->subscriptions()->delete();
        $user = staff($this->tenant, ['view products']);

        expect($user->can('view products'))->toBeFalse()
            ->and(app(Entitlements::class)->isUsable($this->tenant->id))->toBeFalse();
    });

    it('starts a trial or an active period from the plan', function () {
        $service = app(SubscriptionService::class);
        $trial = Plan::factory()->create(['trial_days' => 14]);
        $monthly = Plan::factory()->create(['trial_days' => 0, 'billing_interval' => Plan::MONTHLY]);
        $lifetime = Plan::factory()->create(['billing_interval' => Plan::LIFETIME]);

        $first = $service->start($this->tenant, $trial);
        expect($first->status)->toBe(SubscriptionStatus::Trialing)->and($first->trial_ends_at->isFuture())->toBeTrue();

        $second = $service->start($this->tenant, $monthly);
        expect($second->status)->toBe(SubscriptionStatus::Active)->and($second->ends_at->isFuture())->toBeTrue()
            ->and($first->fresh()->status)->toBe(SubscriptionStatus::Canceled);

        expect($service->start($this->tenant, $lifetime)->ends_at)->toBeNull();
    });
});

describe('limits', function () {
    it('stops adding employees past the plan limit', function () {
        planFor($this->tenant, Module::cases(), ['max_users' => 1]);
        Role::create(['name' => 'Cashier', 'guard_name' => 'web']);
        $owner = staff($this->tenant, ['create users']);

        $this->actingAs($owner)->post(route('users.store'), [
            'name' => 'New', 'email' => 'new@example.com', 'password' => 'Str0ng!Passw0rd#2026',
            'branch_id' => $owner->branch_id, 'role' => 'Cashier', 'is_active' => true,
        ])->assertSessionHas('error');

        expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
    });

    it('stops adding branches past the plan limit', function () {
        planFor($this->tenant, Module::cases(), ['max_branches' => 1]);
        $owner = staff($this->tenant, ['manage branches']);

        $this->actingAs($owner)->post(route('branches.store'), ['name' => 'Second', 'is_active' => true])->assertSessionHas('error');

        expect(Branch::where('tenant_id', $this->tenant->id)->count())->toBe(1);
    });

    it('reports usage against the limits', function () {
        planFor($this->tenant, Module::cases(), ['max_users' => 5]);
        $user = staff($this->tenant, []);

        $this->actingAs($user)->get(route('subscription.show'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('usage.users.limit', 5)
                ->where('usage.users.used', 1)
                ->where('usage.branches.limit', null));
    });
});

describe('platform admin', function () {
    it('creates, updates and deletes plans', function () {
        $payload = [
            'name' => 'Basic', 'slug' => 'basic', 'price' => 10, 'currency' => 'USD', 'billing_interval' => 'monthly',
            'trial_days' => 7, 'modules' => ['pos', 'catalog'], 'max_users' => 3, 'max_branches' => null, 'is_active' => true,
        ];

        $this->actingAs($this->admin)->post(route('admin.plans.store'), $payload)->assertRedirect(route('admin.plans.index'));

        $plan = Plan::where('slug', 'basic')->firstOrFail();
        expect($plan->modules)->toBe(['pos', 'catalog'])->and($plan->max_users)->toBe(3);

        $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), [...$payload, 'name' => 'Basic+', 'modules' => ['pos']])->assertRedirect();
        expect($plan->fresh()->name)->toBe('Basic+')->and($plan->fresh()->modules)->toBe(['pos']);

        $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $plan))->assertSessionHas('success');
        expect(Plan::find($plan->id))->toBeNull();
    });

    it('validates plans', function () {
        $this->actingAs($this->admin)->post(route('admin.plans.store'), [
            'name' => '', 'slug' => 'bad slug!', 'price' => -1, 'currency' => 'US', 'billing_interval' => 'weekly',
            'trial_days' => 999, 'modules' => ['teleport'],
        ])->assertSessionHasErrors(['name', 'slug', 'price', 'currency', 'billing_interval', 'trial_days', 'modules.0']);
    });

    it('does not delete a plan that has subscriptions', function () {
        $subscription = planFor($this->tenant, [Module::Pos]);

        $this->actingAs($this->admin)->delete(route('admin.plans.destroy', $subscription->plan))->assertSessionHas('error');
        expect(Plan::find($subscription->plan_id))->not->toBeNull();
    });

    it('keeps plan management away from tenant users', function () {
        $owner = staff($this->tenant, ['manage settings']);

        $this->actingAs($owner)->get(route('admin.plans.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.tenants.subscription.edit', $this->tenant))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.plans.index'))->assertInertia(fn (Assert $page) => $page->component('Admin/Plans/Index'));
    });

    it('changes a tenant plan, keeping history, and sets overrides', function () {
        $old = planFor($this->tenant, [Module::Pos]);
        $new = Plan::factory()->withModules([Module::Pos, Module::Reports])->create();

        $this->actingAs($this->admin)->put(route('admin.tenants.subscription.update', $this->tenant), [
            'plan_id' => $new->id, 'status' => 'active', 'ends_at' => '2030-01-01T00:00',
            'module_overrides' => ['reports' => null, 'expenses' => true, 'pos' => false], 'notes' => 'Upgraded',
        ])->assertRedirect();

        $current = $this->tenant->subscriptions()->latest('id')->first();
        expect($current->plan_id)->toBe($new->id)
            ->and($current->module_overrides)->toBe(['expenses' => true, 'pos' => false])
            ->and($current->notes)->toBe('Upgraded')
            ->and($this->tenant->subscriptions()->count())->toBe(2)
            ->and($old->fresh()->status)->toBe(SubscriptionStatus::Canceled);

        $this->actingAs($this->admin)->get(route('admin.tenants.subscription.edit', $this->tenant))
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tenants/Subscription')->has('history', 2)->where('subscription.plan_id', $new->id));
    });

    it('requires a plan when creating a tenant', function () {
        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Acme', 'currency' => 'USD', 'admin_name' => 'Boss', 'admin_email' => 'boss@acme.test', 'admin_password' => 'Str0ng!Passw0rd#2026',
        ])->assertSessionHasErrors('plan_id');
    });

    it('starts the chosen plan for a new tenant', function () {
        $plan = Plan::factory()->withModules([Module::Pos])->create(['trial_days' => 10]);

        $this->actingAs($this->admin)->post(route('admin.tenants.store'), [
            'name' => 'Acme', 'currency' => 'USD', 'plan_id' => $plan->id,
            'admin_name' => 'Boss', 'admin_email' => 'boss@acme.test', 'admin_password' => 'Str0ng!Passw0rd#2026',
        ])->assertRedirect(route('admin.tenants.index'));

        $tenant = Tenant::where('name', 'Acme')->firstOrFail();
        $subscription = $tenant->subscriptions()->firstOrFail();
        expect($subscription->plan_id)->toBe($plan->id)->and($subscription->status)->toBe(SubscriptionStatus::Trialing);
    });
});

it('only requires a shift when the plan includes shifts', function () {
    Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => Branch::first()->id, 'type' => 'branch']);
    $this->tenant->update(['require_pos_shift' => true]);
    planFor($this->tenant, [Module::Pos]);
    $cashier = staff($this->tenant, ['access pos']);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 5, 'is_active' => true, 'unlimited_quantity' => true]);

    $this->actingAs($cashier)->postJson(route('pos.checkout'), [
        'items' => [['product_id' => $product->id, 'quantity' => 1]], 'cash_received' => 10,
    ])->assertOk();
});
