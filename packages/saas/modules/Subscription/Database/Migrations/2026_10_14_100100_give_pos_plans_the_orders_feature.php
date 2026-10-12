<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The "pos" plan feature used to include the orders. They are a feature of their own now, so every plan that had the
 * first gets the second, and so does every tenant override that switched the first on or off.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plans')) {
            foreach (DB::table('plans')->get(['id', 'modules']) as $plan) {
                $modules = json_decode((string) $plan->modules, true) ?: [];

                if (in_array('pos', $modules, true) && ! in_array('orders', $modules, true)) {
                    DB::table('plans')->where('id', $plan->id)->update(['modules' => json_encode([...$modules, 'orders'])]);
                }
            }
        }

        if (Schema::hasTable('subscriptions') && Schema::hasColumn('subscriptions', 'module_overrides')) {
            foreach (DB::table('subscriptions')->whereNotNull('module_overrides')->get(['id', 'module_overrides']) as $subscription) {
                $overrides = json_decode((string) $subscription->module_overrides, true) ?: [];

                if (array_key_exists('pos', $overrides) && ! array_key_exists('orders', $overrides)) {
                    $overrides['orders'] = $overrides['pos'];
                    DB::table('subscriptions')->where('id', $subscription->id)->update(['module_overrides' => json_encode($overrides)]);
                }
            }
        }
    }

    public function down(): void {}
};
