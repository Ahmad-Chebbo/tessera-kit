<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Team keeps the company and branch of a user, a role, a profile and a log entry as plain columns, so it works without
 * the Company module. With the module installed they become real foreign keys; tables created before this migration
 * have them already and are left alone. SQLite cannot add a foreign key to an existing table.
 */
return new class extends Migration
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private array $keys = [
        ['users', 'company_id', 'companies', 'nullOnDelete'],
        ['users', 'branch_id', 'branches', 'nullOnDelete'],
        ['roles', 'company_id', 'companies', 'cascadeOnDelete'],
        ['activity_logs', 'company_id', 'companies', 'cascadeOnDelete'],
        ['employee_profiles', 'company_id', 'companies', 'cascadeOnDelete'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->keys as [$table, $column, $references, $onDelete]) {
            if ($this->hasForeignKey($table, $column)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($column, $references, $onDelete): void {
                $blueprint->foreign($column)->references('id')->on($references)->{$onDelete}();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        foreach ($this->keys as [$table, $column]) {
            if ($this->hasForeignKey($table, $column)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropForeign([$column]));
            }
        }
    }

    private function hasForeignKey(string $table, string $column): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn (array $key): bool => in_array($column, $key['columns'], true));
    }
};
