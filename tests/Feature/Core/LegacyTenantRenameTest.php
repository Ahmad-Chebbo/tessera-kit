<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tessera\Core\Database\LegacyTenantRename;

/**
 * Version 3 renamed companies to tenants. A database made by an earlier version is brought along by migrations that use
 * these helpers; a database made by this version has nothing to rename.
 */
it('renames the company column of a table that still has one, once', function () {
    Schema::create('legacy_things', function ($table) {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
    });
    DB::table('legacy_things')->insert(['company_id' => 7]);

    LegacyTenantRename::columns(['legacy_things', 'missing_table']);
    LegacyTenantRename::columns(['legacy_things']);

    expect(Schema::hasColumn('legacy_things', 'company_id'))->toBeFalse()
        ->and(DB::table('legacy_things')->value('tenant_id'))->toBe(7);
});

it('leaves a table that has both columns alone', function () {
    Schema::create('legacy_things', function ($table) {
        $table->id();
        $table->unsignedBigInteger('company_id')->nullable();
        $table->unsignedBigInteger('tenant_id')->nullable();
    });

    LegacyTenantRename::columns(['legacy_things']);

    expect(Schema::hasColumn('legacy_things', 'company_id'))->toBeTrue();
});

it('renames the companies table and keeps its rows', function () {
    $count = DB::table('tenants')->count();
    Schema::rename('tenants', 'companies');

    LegacyTenantRename::table();

    expect(Schema::hasTable('companies'))->toBeFalse()
        ->and(Schema::hasTable('tenants'))->toBeTrue()
        ->and(DB::table('tenants')->count())->toBe($count);
});

it('renames a permission without giving it to anyone who did not have it', function () {
    Permission::create(['name' => 'manage companies', 'guard_name' => 'web']);

    LegacyTenantRename::permission('manage companies', 'manage tenants');

    expect(Permission::where('name', 'manage companies')->exists())->toBeFalse()
        ->and(Permission::where('name', 'manage tenants')->count())->toBe(1);

    Permission::create(['name' => 'manage companies', 'guard_name' => 'web']);
    LegacyTenantRename::permission('manage companies', 'manage tenants');

    expect(Permission::where('name', 'manage companies')->exists())->toBeTrue();
});

it('rewrites the class names stored in a column', function () {
    DB::table('activity_logs')->insert([
        ['event' => 'x', 'description' => 'd', 'subject_type' => 'App\\Modules\\Company\\Models\\Company'],
        ['event' => 'y', 'description' => 'd', 'subject_type' => 'App\\Modules\\Team\\Models\\User'],
    ]);

    LegacyTenantRename::classNames('App\\Modules\\Company\\', 'App\\Modules\\Tenant\\', ['activity_logs' => ['subject_type'], 'nothing' => ['here']]);

    expect(DB::table('activity_logs')->orderBy('id')->pluck('subject_type')->all())
        ->toBe(['App\\Modules\\Tenant\\Models\\Company', 'App\\Modules\\Team\\Models\\User']);
});
