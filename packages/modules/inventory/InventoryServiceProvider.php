<?php

namespace App\Modules\Inventory;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Dashboard\LowStockWidget;
use App\Modules\Inventory\Exports\StockLevelsReport;
use App\Modules\Inventory\Exports\StockMovementsReport;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\ReceivedPurchaseLookup;
use App\Modules\Inventory\Services\StockLocationUsage;
use App\Modules\Tenant\Models\Location;
use Tessera\Core\Contracts\LocationUsage;
use Tessera\Core\Contracts\PurchaseLookup;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Inventory module.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'inventory',
            name: 'Inventory',
            requires: ['catalog', 'tenant', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('inventory', 'Inventory & purchasing', ['view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'create purchases', 'receive purchases', 'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers'], order: 30),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'inventory' => ['view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'create purchases', 'receive purchases', 'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'create purchases', 'receive purchases', 'view suppliers', 'create suppliers', 'edit suppliers', 'delete suppliers'],
            'Manager' => ['view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'create purchases', 'receive purchases', 'view suppliers'],
            'Stock Keeper' => ['view inventory', 'adjust inventory', 'transfer stock', 'view purchases', 'receive purchases'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('inventory', 'nav.inventory', 'inventory.index', 'inventory', 'view inventory', ['inventory.*'], 10),
            new MenuItem('inventory', 'nav.suppliers', 'suppliers.index', 'truck', 'view suppliers', ['suppliers.*'], 20),
            new MenuItem('inventory', 'nav.purchases', 'purchases.index', 'shopping-cart', 'view purchases', ['purchases.*'], 30),
            new MenuItem('inventory', 'inventory.stockMovements', 'inventory.movements', 'transfer', 'view inventory', [], 40),
        ];
    }

    protected function bindings(): void
    {
        $this->app->bind(LocationUsage::class, StockLocationUsage::class);
        $this->app->bind(PurchaseLookup::class, ReceivedPurchaseLookup::class);
    }

    public function boot(): void
    {
        parent::boot();

        Location::resolveRelationUsing('stockMovements', fn (Location $location) => $location->hasMany(StockMovement::class));
        Product::resolveRelationUsing('stockMovements', fn (Product $product) => $product->hasMany(StockMovement::class));
    }

    public function exports(): array
    {
        return [
            StockLevelsReport::class,
            StockMovementsReport::class,
        ];
    }

    public function dashboardWidgets(): array
    {
        return [LowStockWidget::class];
    }
}
