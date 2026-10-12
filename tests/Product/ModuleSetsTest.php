<?php

use Illuminate\Support\Facades\Process;

/**
 * The pages each module serves, reached with a user who holds every permission of the enabled modules.
 *
 * @var array<string, list<string>>
 */
const MODULE_PAGES = [
    'team' => ['users', 'users/create', 'roles', 'roles/create', 'activity'],
    'tenant' => ['settings', 'exchange-rates', 'branches', 'branches/create', 'locations', 'locations/create'],
    'subscription' => ['subscription', 'admin/plans'],
    'auth' => ['profile'],
    'reports' => ['dashboard', 'reports/sales', 'reports/items', 'reports/profit'],
    'catalog' => ['products', 'products/create', 'categories', 'categories/create', 'units'],
    'inventory' => ['inventory', 'inventory/adjust', 'inventory/movements', 'inventory/transfer', 'suppliers', 'suppliers/create', 'purchases', 'purchases/create'],
    'customers' => ['customers', 'customers/create', 'customer-groups'],
    'sales' => ['orders', 'pos', 'pos-shifts', 'pos/held-orders'],
    'discounts' => ['discounts', 'discounts/create', 'discounts/simulator'],
    'expenses' => ['expenses', 'expenses/create', 'expenses/categories', 'expenses/recurring', 'expenses/report', 'expenses/statement'],
    'shifts' => ['shifts'],
    'attendance' => ['attendance', 'attendance/absences', 'attendance/report'],
    'exports' => ['exports'],
];

const ALWAYS_ON = ['team', 'tenant', 'auth'];

/**
 * Migrates an empty database with the given modules enabled, then asks every page of every module for its status.
 *
 * @return array{statuses: array<string, int>, routes: list<string>}
 */
function bootWithModules(string $enabled): array
{
    $database = tempnam(sys_get_temp_dir(), 'modset').'.sqlite';
    touch($database);

    $env = ['MODULES_ENABLED' => $enabled, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];
    $paths = collect(MODULE_PAGES)->flatten()->push('home')->values()->all();

    try {
        $migrate = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'migrate:fresh', '--force']);
        expect($migrate->successful())->toBeTrue($migrate->errorOutput().$migrate->output());

        $routes = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'route:list', '--json']);
        expect($routes->successful())->toBeTrue($routes->errorOutput());

        $smoke = Process::path(base_path())->env([...$env, 'SMOKE_PATHS' => json_encode($paths)])->run([PHP_BINARY, base_path('tests/Support/module-smoke.php')]);
        $output = trim($smoke->output());
        expect($smoke->successful() && str_starts_with($output, '{'))->toBeTrue($smoke->errorOutput().$output);

        return [
            'statuses' => json_decode(substr($output, strpos($output, '{')), true),
            'routes' => array_column(json_decode($routes->output(), true), 'name'),
        ];
    } finally {
        @unlink($database);
    }
}

dataset('module sets', [
    'one business, no modules' => ['none', []],
    'saas on its own' => ['subscription', ['subscription']],
    'employee management' => ['shifts,attendance', ['shifts', 'attendance']],
    'catalog and inventory' => ['catalog,inventory', ['catalog', 'inventory']],
    'expenses on their own' => ['expenses', ['expenses']],
    'customers on their own' => ['customers', ['customers']],
    'catalog on its own' => ['catalog', ['catalog']],
    'shifts on their own' => ['shifts', ['shifts']],
    'exports on their own' => ['exports', ['exports']],
    'discounts with what they need' => ['catalog,inventory,customers,sales,discounts', ['catalog', 'inventory', 'customers', 'sales', 'discounts']],
    'expenses with reports and exports' => ['catalog,customers,inventory,sales,reports,expenses,exports', ['catalog', 'inventory', 'customers', 'sales', 'reports', 'expenses', 'exports']],
    'point of sale' => ['catalog,inventory,customers,sales', ['catalog', 'inventory', 'customers', 'sales']],
    'point of sale as a saas' => ['catalog,inventory,customers,sales,subscription', ['catalog', 'inventory', 'customers', 'sales', 'subscription']],
    'reports on sales' => ['catalog,customers,inventory,sales,reports', ['catalog', 'inventory', 'customers', 'sales', 'reports']],
    'every module' => ['*', ['catalog', 'inventory', 'customers', 'sales', 'reports', 'discounts', 'expenses', 'shifts', 'attendance', 'exports', 'subscription']],
]);

it('boots, migrates and serves exactly the pages of the enabled modules', function (string $enabled, array $features) {
    $result = bootWithModules($enabled);
    $served = [...ALWAYS_ON, ...$features];

    foreach (MODULE_PAGES as $module => $pages) {
        foreach ($pages as $page) {
            $status = $result['statuses'][$page] ?? null;

            if (in_array($module, $served, true)) {
                expect($status)->toBe(200, "{$page} should be served by the {$module} module");
            } else {
                expect($status)->toBe(404, "{$page} should not exist without the {$module} module");
            }
        }
    }

    expect($result['statuses']['home'])->toBe(302);

    $tenancy = $result['statuses']['_tenancy'];

    expect($tenancy['tenants'])->toBe(1)
        ->and($tenancy['user_tenant'])->not->toBeNull()
        ->and($tenancy['user_branch'])->not->toBeNull();

    if (! in_array('subscription', $served, true)) {
        expect($tenancy['tenant_roles'])->toBe(0, 'without the Subscription module the roles are shared');
    }
})->with('module sets');
