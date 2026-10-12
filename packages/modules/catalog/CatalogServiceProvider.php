<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Database\Seeders\UnitSeeder;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Products, categories and units. Stock levels come from Inventory through a contract.
 */
class CatalogServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'catalog',
            name: 'Catalog',
            requires: ['company'],
            packages: ['spatie/laravel-medialibrary'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('catalog', 'Products & categories', ['view products', 'create products', 'edit products', 'delete products', 'view categories', 'create categories', 'edit categories', 'delete categories'], order: 20),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'catalog' => ['view products', 'create products', 'edit products', 'delete products', 'view categories', 'create categories', 'edit categories', 'delete categories'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view products', 'create products', 'edit products', 'delete products', 'view categories', 'create categories', 'edit categories', 'delete categories'],
            'Manager' => ['view products', 'create products', 'edit products', 'view categories'],
            'Stock Keeper' => ['view products', 'view categories'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('catalog', 'nav.products', 'products.index', 'package', 'view products', ['products.*'], 10),
            new MenuItem('catalog', 'nav.categories', 'categories.index', 'tag', 'view categories', ['categories.*'], 20),
        ];
    }

    public function seeders(): array
    {
        return [UnitSeeder::class];
    }
}
