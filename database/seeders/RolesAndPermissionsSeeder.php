<?php

namespace Database\Seeders;

use App\Modules\Team\Services\PermissionSynchronizer;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * The permissions and the default role permissions are declared by the modules; this only applies them.
     */
    public function run(): void
    {
        $result = app(PermissionSynchronizer::class)->sync();

        $this->command?->info('Roles and permissions seeded successfully!');
        $this->command?->info('Roles: '.implode(', ', $result['roles']));
    }
}
