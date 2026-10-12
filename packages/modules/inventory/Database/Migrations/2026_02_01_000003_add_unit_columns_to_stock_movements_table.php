<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            // Change quantity from integer to decimal for fractional base-unit quantities
            $table->decimal('quantity', 15, 4)->change();

            // Unit tracking columns
            $table->foreignId('unit_id')->nullable()->after('quantity')->constrained('units')->nullOnDelete();
            $table->decimal('unit_quantity', 15, 4)->nullable()->after('unit_id');
            $table->decimal('unit_to_base_factor', 15, 4)->nullable()->after('unit_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['unit_id']);
            $table->dropColumn(['unit_id', 'unit_quantity', 'unit_to_base_factor']);
            $table->integer('quantity')->change();
        });
    }
};
