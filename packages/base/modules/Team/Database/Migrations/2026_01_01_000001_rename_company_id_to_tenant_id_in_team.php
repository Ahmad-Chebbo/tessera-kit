<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\LegacyTenantRename;

return new class extends Migration
{
    public function up(): void
    {
        LegacyTenantRename::columns(['users', 'roles', 'activity_logs', 'employee_profiles']);
    }

    public function down(): void {}
};
