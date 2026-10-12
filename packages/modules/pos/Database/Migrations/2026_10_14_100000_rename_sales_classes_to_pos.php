<?php

use Illuminate\Database\Migrations\Migration;
use Tessera\Core\Database\StoredClassNames;

/**
 * PosShift and HeldOrder moved from the Sales module to the Pos module.
 */
return new class extends Migration
{
    public function up(): void
    {
        StoredClassNames::rewrite('App\\Modules\\Sales\\Models\\PosShift', 'App\\Modules\\Pos\\Models\\PosShift');
        StoredClassNames::rewrite('App\\Modules\\Sales\\Models\\HeldOrder', 'App\\Modules\\Pos\\Models\\HeldOrder');
    }

    public function down(): void {}
};
