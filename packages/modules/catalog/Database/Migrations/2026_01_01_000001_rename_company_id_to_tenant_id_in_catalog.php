<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\LegacyTenantRename;

return new class extends Migration
{
    public function up(): void
    {
        LegacyTenantRename::columns(['categories', 'products', 'units']);
    }

    public function down(): void {}
};
