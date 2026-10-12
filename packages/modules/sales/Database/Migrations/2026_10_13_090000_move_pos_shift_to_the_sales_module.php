<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PosShift moved from the Shifts module to the Sales module. Polymorphic columns store class names, so rows written
 * before the move are rewritten to the new name.
 */
return new class extends Migration
{
    private const OLD = 'App\\Modules\\Shifts\\Models\\PosShift';

    private const NEW = 'App\\Modules\\Sales\\Models\\PosShift';

    /**
     * @var array<string, string>
     */
    private array $columns = [
        'model_has_roles' => 'model_type',
        'model_has_permissions' => 'model_type',
        'media' => 'model_type',
        'activity_logs' => 'subject_type',
        'stock_movements' => 'reference_type',
        'personal_access_tokens' => 'tokenable_type',
        'notifications' => 'notifiable_type',
    ];

    public function up(): void
    {
        $this->rewrite(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->rewrite(self::NEW, self::OLD);
    }

    private function rewrite(string $from, string $to): void
    {
        foreach ($this->columns as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
