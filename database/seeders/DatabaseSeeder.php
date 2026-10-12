<?php

namespace Database\Seeders;

use App\Modules\Team\Models\Role;
use App\Modules\Team\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Tessera\Engine\ModuleRegistry;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the roles and the first account, then whatever the enabled modules seed (plans, units, a demo store with its
     * own owner...). With the Subscription module the account is the platform Admin, who belongs to no tenant and manages
     * tenants and plans; without it, the Owner, who holds every permission the installed modules give.
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@app.com',
        ]);

        $this->call(RolesAndPermissionsSeeder::class);

        if (app(ModuleRegistry::class)->has('subscription')) {
            $user->assignRole('Admin');
        } else {
            Role::findByName('Owner')->syncPermissions(Permission::all());
            $user->assignRole('Owner');
        }

        foreach (app(ModuleRegistry::class)->seeders() as $seeder) {
            $this->call($seeder);
        }
    }
}
