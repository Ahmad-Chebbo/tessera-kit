<?php

namespace App\Modules\Sales;

use App\Modules\Sales\Database\Seeders\DemoStoreSeeder;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;

/**
 * Sales: the till and the orders together, for a project that wants both. It adds nothing of its own except a demo
 * store to try them with; install Orders or Pos alone when only one is needed.
 */
class SalesServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'sales',
            name: 'Sales',
            requires: ['orders', 'pos'],
        );
    }

    public function seeders(): array
    {
        return [DemoStoreSeeder::class];
    }
}
