<?php

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tessera\Engine\ModuleRegistry;

const PERMISSIONS_BEFORE_MODULES = [
    'view dashboard', 'access pos', 'apply discounts', 'void orders',
    'view products', 'create products', 'edit products', 'delete products',
    'view categories', 'create categories', 'edit categories', 'delete categories',
    'view orders', 'view all orders', 'export orders',
    'view inventory', 'adjust inventory', 'transfer stock',
    'view purchases', 'create purchases', 'receive purchases',
    'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers',
    'view expenses', 'create expenses', 'edit expenses', 'delete expenses',
    'view reports', 'view sales reports', 'view profit reports', 'view inventory reports',
    'view users', 'create users', 'edit users', 'delete users',
    'view customers', 'create customers', 'edit customers', 'delete customers',
    'manage settings', 'manage companies', 'manage branches', 'manage locations',
    'view all branches', 'export reports', 'manage discounts', 'manage roles', 'view activity log',
    'view shifts', 'manage shifts', 'view shift reports', 'open pos shift',
    'view attendance', 'manage attendance', 'record attendance',
];

function navigationOf(User $user, string $route = 'users.index'): array
{
    $captured = [];

    test()->actingAs($user)->get(route($route))->assertInertia(function (Assert $page) use (&$captured) {
        $captured = $page->toArray()['props']['navigation'];

        return $page;
    });

    return $captured;
}

function navigationLabels(array $navigation): array
{
    return collect($navigation)->flatMap(fn (array $entry): array => isset($entry['section']) ? array_column($entry['items'], 'label') : [$entry['label']])->all();
}

describe('permissions', function () {
    it('declares exactly the permissions the seeder used to create', function () {
        expect(app(ModuleRegistry::class)->permissions())->toEqualCanonicalizing(PERMISSIONS_BEFORE_MODULES);
    });

    it('keeps the plan feature keys and their order', function () {
        expect(array_keys(app(ModuleRegistry::class)->features()))->toBe(['pos', 'catalog', 'inventory', 'customers', 'expenses', 'reports', 'branches', 'shifts', 'attendance', 'activity', 'roles', 'discounts', 'exports'])
            ->and(Module::values())->toBe(array_keys(app(ModuleRegistry::class)->features()));
    });

    it('switches each permission with the same plan feature as before', function () {
        $registry = app(ModuleRegistry::class);

        expect($registry->featureForPermission('access pos'))->toBe('pos')
            ->and($registry->featureForPermission('view all orders'))->toBe('pos')
            ->and($registry->featureForPermission('open pos shift'))->toBe('shifts')
            ->and($registry->featureForPermission('view shift reports'))->toBe('shifts')
            ->and($registry->featureForPermission('manage shifts'))->toBe('shifts')
            ->and($registry->featureForPermission('manage locations'))->toBe('branches')
            ->and($registry->featureForPermission('view activity log'))->toBe('activity')
            ->and($registry->featureForPermission('export reports'))->toBe('exports')
            ->and($registry->featureForPermission('view dashboard'))->toBeNull()
            ->and($registry->featureForPermission('manage settings'))->toBeNull()
            ->and($registry->featureForPermission('view users'))->toBeNull();
    });

    it('labels features for plan screens', function () {
        expect(Module::Pos->label())->toBe('Point of sale & orders')
            ->and(Module::Shifts->label())->toBe('Shifts & cash drawer')
            ->and(Module::Exports->permissions())->toBe(['export reports']);
    });

    it('lists the role editor groups in their usual order', function () {
        expect(array_keys(app(ModuleRegistry::class)->permissionGroups()))->toBe(['dashboard', 'pos', 'catalog', 'sales', 'inventory', 'expenses', 'reports', 'team', 'shifts', 'attendance', 'settings'])
            ->and(app(ModuleRegistry::class)->permissionGroups()['sales'])->toContain('view orders', 'view customers', 'manage discounts');
    });
});

describe('default roles', function () {
    it('gives the template roles the permissions the seeder used to give', function () {
        $roles = app(ModuleRegistry::class)->defaultRolePermissions();

        $team = ['view activity log', 'view shifts', 'manage shifts', 'view shift reports', 'open pos shift', 'view attendance', 'manage attendance', 'record attendance'];

        expect($roles['Admin'])->toBe(['manage companies'])
            ->and($roles['Cashier'])->toEqualCanonicalizing(['access pos', 'view customers', 'create customers', 'open pos shift', 'record attendance'])
            ->and($roles['Stock Keeper'])->toEqualCanonicalizing(['view dashboard', 'view products', 'view categories', 'view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'receive purchases', 'view inventory reports', 'record attendance', 'view customers'])
            ->and($roles['Manager'])->toEqualCanonicalizing([
                'view dashboard', 'access pos', 'apply discounts', 'void orders', 'view products', 'create products', 'edit products', 'view categories',
                'view orders', 'view all orders', 'export orders', 'view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'create purchases',
                'receive purchases', 'view suppliers', 'view expenses', 'create expenses', 'view reports', 'view sales reports', 'view profit reports',
                'view inventory reports', 'manage branches', 'manage locations', 'view customers', 'create customers', 'edit customers', 'delete customers',
                'manage discounts', 'export reports', 'view all branches', ...$team,
            ])
            ->and($roles['Owner'])->toContain('manage roles', 'manage settings', 'view all branches', 'delete products', ...$team)
            ->not->toContain('manage companies', 'delete users');
    });

    it('creates the permissions and template roles when synced, and nothing twice', function () {
        $before = Permission::count();
        $first = app(PermissionSynchronizer::class)->sync();
        $second = app(PermissionSynchronizer::class)->sync();

        expect($first['created'])->toHaveCount(count(PERMISSIONS_BEFORE_MODULES) - $before)
            ->and($second['created'])->toBe([])
            ->and(Permission::count())->toBe(count(PERMISSIONS_BEFORE_MODULES))
            ->and(Role::whereNull('company_id')->whereIn('name', ['Admin', 'Owner', 'Manager', 'Cashier', 'Stock Keeper'])->count())->toBe(5)
            ->and(Role::whereNull('company_id')->where('name', 'Cashier')->first()->hasPermissionTo('access pos'))->toBeTrue();
    });

    it('hands a new permission to company roles but never re-adds one a company removed', function () {
        app(PermissionSynchronizer::class)->sync();

        $company = Company::factory()->create();
        $owner = Role::query()->firstOrCreate(['name' => 'Owner', 'guard_name' => 'web', 'company_id' => $company->id]);
        $owner->givePermissionTo('view products');

        Permission::where('name', 'view all branches')->delete();
        app(PermissionSynchronizer::class)->sync();

        expect($owner->fresh()->hasPermissionTo('view all branches'))->toBeTrue()
            ->and($owner->fresh()->hasPermissionTo('delete products'))->toBeFalse();
    });

    it('runs from the console and the seeder', function () {
        $this->artisan('modules:sync')->expectsOutputToContain('permissions created')->assertSuccessful();
        $this->seed(RolesAndPermissionsSeeder::class);

        expect(Permission::count())->toBe(count(PERMISSIONS_BEFORE_MODULES));
    });
});

describe('sidebar', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
        $this->staff = fn (array $permissions) => User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id])->givePermissionTo(
            collect($permissions)->map(fn (string $name) => Permission::findOrCreate($name))->all()
        );
    });

    it('shows only what the user may open, in sections', function () {
        $navigation = navigationOf(($this->staff)(['view users', 'view products', 'view categories', 'manage settings']));

        expect($navigation)->toHaveCount(3)
            ->and($navigation[0]['section'])->toBe('CATALOG')
            ->and(array_column($navigation[0]['items'], 'label'))->toBe(['nav.products', 'nav.categories'])
            ->and($navigation[1]['section'])->toBe('TEAM')
            ->and(array_column($navigation[1]['items'], 'label'))->toBe(['users.title'])
            ->and($navigation[2]['section'])->toBe('SETTINGS')
            ->and(navigationLabels($navigation))->not->toContain('nav.orders', 'expenses.nav');
    });

    it('puts dashboard and POS above the sections and marks the current page', function () {
        $navigation = navigationOf(($this->staff)(['view users', 'view dashboard', 'access pos']));

        expect(array_column(array_slice($navigation, 0, 2), 'label'))->toBe(['nav.dashboard', 'nav.pos'])
            ->and($navigation[0]['href'])->toBe('/dashboard')
            ->and($navigation[0]['active'])->toBeFalse()
            ->and(collect($navigation)->firstWhere('section', 'TEAM')['items'][0]['active'])->toBeTrue();
    });

    it('hides features the plan leaves out', function () {
        $plan = Plan::factory()->withModules([Module::Catalog])->create();
        app(SubscriptionService::class)->start($this->company, $plan);

        $navigation = navigationOf(($this->staff)(['view users', 'view products', 'view expenses', 'view orders']));

        expect(navigationLabels($navigation))->toContain('nav.products', 'users.title')->not->toContain('expenses.nav', 'nav.orders');
    });

    it('shows everything to a super admin', function () {
        $admin = User::factory()->create(['company_id' => null]);
        $admin->givePermissionTo(Permission::findOrCreate('manage companies'));
        $admin->assignRole(Role::query()->firstOrCreate(['name' => 'Admin', 'guard_name' => 'web', 'company_id' => null]));

        $labels = navigationLabels(navigationOf($admin, 'admin.companies.index'));

        expect($labels)->toContain('nav.companies', 'plans.nav', 'nav.products', 'expenses.nav', 'attendance.historyNav');
    });
});
