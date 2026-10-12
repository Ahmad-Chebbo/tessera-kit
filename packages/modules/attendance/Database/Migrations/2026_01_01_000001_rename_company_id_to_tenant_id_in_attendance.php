<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\LegacyTenantRename;

return new class extends Migration
{
    public function up(): void
    {
        LegacyTenantRename::columns(['attendance_records', 'attendance_excuses']);
    }

    public function down(): void {}
};
