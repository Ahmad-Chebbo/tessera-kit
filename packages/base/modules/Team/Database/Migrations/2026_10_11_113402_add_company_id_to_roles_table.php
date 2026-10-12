<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $templates = ['Owner', 'Manager', 'Cashier', 'Stock Keeper'];

    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        $templates = DB::table('roles')->whereNull('tenant_id')->whereIn('name', $this->templates)->get();

        if ($templates->isEmpty() || ! Schema::hasTable('tenants')) {
            return;
        }

        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach ($templates as $template) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => $template->name,
                    'guard_name' => $template->guard_name,
                    'tenant_id' => $tenantId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $permissions = DB::table('role_has_permissions')->where('role_id', $template->id)->pluck('permission_id');

                DB::table('role_has_permissions')->insert(
                    $permissions->map(fn ($permissionId): array => ['permission_id' => $permissionId, 'role_id' => $roleId])->all(),
                );

                DB::table('model_has_roles')
                    ->where('role_id', $template->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->whereIn('model_id', DB::table('users')->where('tenant_id', $tenantId)->select('id'))
                    ->update(['role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $templates = DB::table('roles')->whereNull('tenant_id')->whereIn('name', $this->templates)->get()->keyBy('name');

        foreach (DB::table('roles')->whereNotNull('tenant_id')->get() as $role) {
            if ($templates->has($role->name)) {
                DB::table('model_has_roles')->where('role_id', $role->id)->update(['role_id' => $templates[$role->name]->id]);
            }
        }

        DB::table('roles')->whereNotNull('tenant_id')->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'name', 'guard_name']);
            $table->unique(['name', 'guard_name']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('tenant_id');
        });
    }
};
