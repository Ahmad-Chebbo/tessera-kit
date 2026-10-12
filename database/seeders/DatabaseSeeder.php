<?php

namespace Database\Seeders;

use App\Modules\Team\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Tessera\Engine\ModuleRegistry;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the roles and an admin account, then whatever the enabled modules seed (plans, units, a demo store...).
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@app.com',
        ]);

        $this->call(RolesAndPermissionsSeeder::class);

        $user->assignRole('Owner');

        foreach (app(ModuleRegistry::class)->seeders() as $seeder) {
            $this->call($seeder);
        }
    }
}
