<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\LegacyTenantRename;

return new class extends Migration
{
    public function up(): void
    {
        LegacyTenantRename::columns(['shift_templates', 'shift_cycles', 'shift_assignments']);
    }

    public function down(): void {}
};
