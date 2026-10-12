<?php

namespace App\Modules\Catalog\Database\Seeders;

use App\Modules\Catalog\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Gram', 'abbreviation' => 'g'],
            ['name' => 'Kilogram', 'abbreviation' => 'kg'],
            ['name' => 'Liter', 'abbreviation' => 'L'],
            ['name' => 'Milliliter', 'abbreviation' => 'mL'],
            ['name' => 'Cup', 'abbreviation' => 'cup'],
            ['name' => 'Bag', 'abbreviation' => 'bag'],
            ['name' => 'Box', 'abbreviation' => 'box'],
            ['name' => 'Crate', 'abbreviation' => 'crate'],
            ['name' => 'Sachet', 'abbreviation' => 'sachet'],
            ['name' => 'Dozen', 'abbreviation' => 'dz'],
            ['name' => 'Carton', 'abbreviation' => 'ctn'],
            ['name' => 'Pack', 'abbreviation' => 'pack'],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(
                ['name' => $unit['name'], 'company_id' => null],
                ['abbreviation' => $unit['abbreviation'], 'is_active' => true]
            );
        }

        $this->command->info('Global units seeded successfully!');
    }
}
