<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->boolean('require_pos_shift')->default(false);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('pos_shift_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_shift_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('require_pos_shift');
        });
    }
};
