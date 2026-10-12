<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(100);
            $table->boolean('stackable')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('conditions')->nullable();
            $table->text('expression')->nullable();
            $table->string('action_type', 24);
            $table->decimal('action_value', 12, 2)->default(0);
            $table->json('action_config')->nullable();
            $table->string('coupon_code', 50)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_customer_limit')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'priority']);
            $table->unique(['tenant_id', 'coupon_code']);
        });

        Schema::create('discount_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('discount_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rule_name', 150);
            $table->string('coupon_code', 50)->nullable();
            $table->decimal('amount', 12, 2);
            $table->json('details')->nullable();
            $table->timestamps();

            $table->index(['discount_rule_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_applications');
        Schema::dropIfExists('discount_rules');
    }
};
