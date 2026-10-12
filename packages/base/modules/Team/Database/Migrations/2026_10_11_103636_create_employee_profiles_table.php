<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('employee_number', 32);
            $table->string('phone', 50)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->date('hire_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id', 64)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact_name', 100)->nullable();
            $table->string('emergency_contact_phone', 50)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'employee_number']);
        });

        $counters = [];

        DB::table('users')->whereNotNull('tenant_id')->orderBy('id')->each(function ($user) use (&$counters) {
            $counters[$user->tenant_id] = ($counters[$user->tenant_id] ?? 0) + 1;

            DB::table('employee_profiles')->insert([
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'employee_number' => 'EMP-'.str_pad((string) $counters[$user->tenant_id], 4, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
