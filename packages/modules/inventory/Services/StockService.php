<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Tenant\Models\Location;
use Illuminate\Support\Collection;

class StockService
{
    /**
     * Get stock quantity (in base units) for a product at a specific location.
     * Stock is always calculated from movements, never stored directly.
     */
    public function getStockAtLocation(Product $product, Location $location): float
    {
        return (float) StockMovement::query()
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->sum('quantity');
    }

    /**
     * Get total stock (in base units) for a product across all locations.
     */
    public function getTotalStock(Product $product): float
    {
        return (float) StockMovement::query()
            ->where('product_id', $product->id)
            ->sum('quantity');
    }

    /**
     * Get stock levels for all products at a specific location.
     *
     * @return Collection<int, array{product_id: int, quantity: float}>
     */
    public function getStockByLocation(Location $location): Collection
    {
        return StockMovement::query()
            ->where('location_id', $location->id)
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'quantity' => (float) $row->quantity,
            ]);
    }

    /**
     * Check if sufficient stock (in base units) exists at a location.
     */
    public function hasSufficientStock(Product $product, Location $location, float $requiredQuantity): bool
    {
        return $this->getStockAtLocation($product, $location) >= $requiredQuantity;
    }

    /**
     * Get stock levels (in base units) for multiple products at a location.
     *
     * @param  array<int>|null  $productIds
     * @param  array<int>|null  $locationIds  Restricts the sum to these locations when no single location is given.
     * @return Collection<int, float>
     */
    public function getStockForProducts(?array $productIds = null, ?Location $location = null, ?array $locationIds = null): Collection
    {
        return StockMovement::query()
            ->when($location, fn ($query) => $query->where('location_id', $location->id))
            ->when(! $location && $locationIds !== null, fn ($query) => $query->whereIn('location_id', $locationIds))
            ->when($productIds, fn ($query) => $query->whereIn('product_id', $productIds))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as quantity')
            ->pluck('quantity', 'product_id')
            ->map(fn ($qty) => (float) $qty);
    }
}
