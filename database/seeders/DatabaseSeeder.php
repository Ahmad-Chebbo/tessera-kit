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
     * Seeds the roles and the platform admin (no company; it manages companies and plans), then whatever the enabled
     * modules seed (plans, units, a demo store with its own owner...).
     */
    public function run(): void
    {
        $user = User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@app.com',
        ]);

        $this->call(RolesAndPermissionsSeeder::class);

        $user->assignRole('Admin');

        foreach (app(ModuleRegistry::class)->seeders() as $seeder) {
            $this->call($seeder);
        }
    }
}
