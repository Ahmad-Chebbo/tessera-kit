<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\LegacyTenantRename;

return new class extends Migration
{
    public function up(): void
    {
        LegacyTenantRename::table();
        LegacyTenantRename::permission('manage companies', 'manage tenants');
        LegacyTenantRename::classNames('App\\Modules\\Company\\', 'App\\Modules\\Tenant\\', ['activity_logs' => ['subject_type'], 'media' => ['model_type']]);
        LegacyTenantRename::columns(['branches', 'locations', 'exchange_rates']);
    }

    public function down(): void {}
};
