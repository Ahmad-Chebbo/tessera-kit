<?php

use App\Modules\Tenant\Models\Tenant;
use App\Modules\Tenant\Services\TenantAccess;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users, roles, employee profiles and the activity log have no tenant of their own in the base package. This gives them
 * the tenant (and users their branch) when the Tenant module is installed. A database made by an earlier version has
 * the columns already and is left as it is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id')->index();
            });
        }

        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('tenant_id')->index();
            });
        }

        if (! Schema::hasColumn('roles', 'tenant_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
            });

            Schema::table('roles', function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique(['tenant_id', 'name', 'guard_name']);
            });
        }

        if (! Schema::hasColumn('employee_profiles', 'tenant_id')) {
            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
            });

            Schema::table('employee_profiles', function (Blueprint $table) {
                $table->dropUnique(['employee_number']);
                $table->unique(['tenant_id', 'employee_number']);
            });
        }

        if (! Schema::hasColumn('activity_logs', 'tenant_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->index(['tenant_id', 'created_at']);
            });
        }

        if (! app(TenantAccess::class)->isMultiTenant() && DB::table('users')->whereNull('tenant_id')->exists()) {
            $tenant = Tenant::default();

            DB::table('users')->whereNull('tenant_id')->update(['tenant_id' => $tenant->id, 'branch_id' => $tenant->mainBranch()?->id]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'created_at']);
            $table->dropColumn('tenant_id');
        });

        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'employee_number']);
            $table->dropColumn('tenant_id');
            $table->unique(['employee_number']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
            $table->dropColumn('tenant_id');
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['tenant_id']);
            $table->dropIndex(['branch_id']);
            $table->dropColumn(['tenant_id', 'branch_id']);
        });
    }
};
