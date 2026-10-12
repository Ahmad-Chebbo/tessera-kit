<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expense_categories', function (Blueprint $table) {
            $table->string('type', 24)->default('operating');
            $table->boolean('affects_profit')->default(true);
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $purchase = $table->foreignId('purchase_id')->nullable()->unique();

            if (Schema::hasTable('purchases')) {
                $purchase->constrained()->nullOnDelete();
            }
            $table->foreignId('recurring_expense_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('spread_months')->nullable();

            $table->unique(['recurring_expense_id', 'expense_date']);
            $table->index(['company_id', 'expense_date']);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropUnique(['recurring_expense_id', 'expense_date']);
            $table->dropIndex(['company_id', 'expense_date']);
            $table->dropConstrainedForeignId('recurring_expense_id');
            $table->dropConstrainedForeignId('purchase_id');
            $table->dropConstrainedForeignId('employee_id');
            $table->dropColumn('spread_months');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->dropColumn(['type', 'affects_profit']);
        });
    }
};
