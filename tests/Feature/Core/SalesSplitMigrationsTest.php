<?php

use App\Modules\Subscription\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sales was split into Orders and Pos. A database made before the split stores the old class names and the old plan
 * feature; these migrations bring it along.
 */
function runMigration(string $path): void
{
    /** @var Migration $migration */
    $migration = require base_path($path);
    $migration->up();
}

function storedSubjects(): array
{
    return DB::table('activity_logs')->orderBy('id')->pluck('subject_type')->all();
}

it('rewrites the stored class names of orders, lines and payments', function () {
    foreach (['Order', 'OrderItem', 'Payment', 'Customer'] as $class) {
        DB::table('activity_logs')->insert(['event' => 'x', 'description' => 'd', 'subject_type' => 'App\\Modules\\Sales\\Models\\'.$class]);
    }

    runMigration('packages/modules/orders/Database/Migrations/2026_10_14_100000_rename_sales_classes_to_orders.php');

    expect(storedSubjects())->toBe([
        'App\\Modules\\Orders\\Models\\Order',
        'App\\Modules\\Orders\\Models\\OrderItem',
        'App\\Modules\\Orders\\Models\\Payment',
        'App\\Modules\\Sales\\Models\\Customer',
    ]);
});

it('rewrites the stored class names of shifts and held orders, and of stock movements', function () {
    foreach (['PosShift', 'HeldOrder'] as $class) {
        DB::table('activity_logs')->insert(['event' => 'x', 'description' => 'd', 'subject_type' => 'App\\Modules\\Sales\\Models\\'.$class]);
    }

    runMigration('packages/modules/pos/Database/Migrations/2026_10_14_100000_rename_sales_classes_to_pos.php');

    expect(storedSubjects())->toBe(['App\\Modules\\Pos\\Models\\PosShift', 'App\\Modules\\Pos\\Models\\HeldOrder']);
});

it('leaves a database made after the split as it is', function () {
    DB::table('activity_logs')->insert(['event' => 'x', 'description' => 'd', 'subject_type' => 'App\\Modules\\Orders\\Models\\Order']);

    runMigration('packages/modules/orders/Database/Migrations/2026_10_14_100000_rename_sales_classes_to_orders.php');
    runMigration('packages/modules/pos/Database/Migrations/2026_10_14_100000_rename_sales_classes_to_pos.php');

    expect(storedSubjects())->toBe(['App\\Modules\\Orders\\Models\\Order']);
});

it('gives every plan that had the till the orders feature too, once', function () {
    $till = Plan::factory()->create(['modules' => ['pos', 'catalog']]);
    $both = Plan::factory()->create(['modules' => ['pos', 'orders']]);
    $neither = Plan::factory()->create(['modules' => ['catalog']]);

    runMigration('packages/saas/modules/Subscription/Database/Migrations/2026_10_14_100100_give_pos_plans_the_orders_feature.php');
    runMigration('packages/saas/modules/Subscription/Database/Migrations/2026_10_14_100100_give_pos_plans_the_orders_feature.php');

    expect($till->fresh()->modules)->toBe(['pos', 'catalog', 'orders'])
        ->and($both->fresh()->modules)->toBe(['pos', 'orders'])
        ->and($neither->fresh()->modules)->toBe(['catalog']);
});

it('copies a tenant override of the till feature to the orders feature', function () {
    $tenant = Tenant::factory()->create();
    DB::table('subscriptions')->where('tenant_id', $tenant->id)->update(['module_overrides' => json_encode(['pos' => false, 'catalog' => true])]);

    runMigration('packages/saas/modules/Subscription/Database/Migrations/2026_10_14_100100_give_pos_plans_the_orders_feature.php');

    $overrides = json_decode((string) DB::table('subscriptions')->where('tenant_id', $tenant->id)->value('module_overrides'), true);

    expect($overrides)->toBe(['pos' => false, 'catalog' => true, 'orders' => false]);
});
