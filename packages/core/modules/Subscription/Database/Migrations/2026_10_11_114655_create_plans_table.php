<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('billing_interval', 16)->default('monthly');
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('modules');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_branches')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $modules = ['pos', 'catalog', 'inventory', 'customers', 'expenses', 'reports', 'branches', 'shifts', 'attendance', 'activity', 'roles'];

        DB::table('plans')->insert([
            'name' => 'Legacy (all modules)',
            'slug' => 'legacy',
            'description' => 'Given to companies that existed before plans were introduced.',
            'price' => 0,
            'billing_interval' => 'lifetime',
            'modules' => json_encode($modules),
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
