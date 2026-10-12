<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->after('name');
            $table->string('locale', 5)->default('en')->after('currency');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('locale');
            $table->string('timezone')->default('UTC')->after('tax_rate');
            $table->text('receipt_header')->nullable()->after('timezone');
            $table->text('receipt_footer')->nullable()->after('receipt_header');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['currency', 'locale', 'tax_rate', 'timezone', 'receipt_header', 'receipt_footer']);
        });
    }
};
