<?php

namespace Tessera\Core\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tessera 3 renamed companies to tenants. These helpers bring a database made by an earlier version along: a table or a
 * column that still has the old name is renamed, and a database made by this version has nothing to rename. The
 * migrations that call them sort before every other migration, so a module installed later finds its tables ready.
 */
final class LegacyTenantRename
{
    /**
     * Renames the company_id column of each table that still has one.
     *
     * @param  list<string>  $tables
     */
    public static function columns(array $tables): void
    {
        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'company_id') && ! Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, fn ($blueprint) => $blueprint->renameColumn('company_id', 'tenant_id'));
            }
        }
    }

    /**
     * Renames the companies table, which the tenant column of every other table points at.
     */
    public static function table(): void
    {
        if (Schema::hasTable('companies') && ! Schema::hasTable('tenants')) {
            Schema::rename('companies', 'tenants');
        }
    }

    /**
     * Renames a permission without handing it to anyone who did not have it.
     */
    public static function permission(string $old, string $new): void
    {
        if (Schema::hasTable('permissions') && ! DB::table('permissions')->where('name', $new)->exists()) {
            DB::table('permissions')->where('name', $old)->update(['name' => $new]);
        }
    }

    /**
     * Rewrites class names stored in the database, where a model class moved from one namespace to another.
     *
     * @param  array<string, list<string>>  $columns  table => columns that hold class names
     */
    public static function classNames(string $from, string $to, array $columns): void
    {
        foreach ($columns as $table => $names) {
            foreach ($names as $column) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                    DB::table($table)->where($column, 'like', $from.'%')->update([$column => DB::raw('REPLACE('.$column.', '.DB::getPdo()->quote($from).', '.DB::getPdo()->quote($to).')')]);
                }
            }
        }
    }
}
