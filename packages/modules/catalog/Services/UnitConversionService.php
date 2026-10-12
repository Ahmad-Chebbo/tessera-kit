<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\ProductUnit;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UnitConversionService
{
    /**
     * Convert a quantity from a given unit to the product's base unit.
     *
     * @return array{base_quantity: float, factor: float}
     */
    public function convertToBase(int $productId, int $unitId, float $quantity): array
    {
        $productUnit = $this->getProductUnit($productId, $unitId);

        return [
            'base_quantity' => $productUnit->convertToBase($quantity),
            'factor' => (float) $productUnit->to_base_factor,
        ];
    }

    /**
     * Convert a base-unit quantity to the given unit.
     */
    public function convertFromBase(int $productId, int $unitId, float $baseQuantity): float
    {
        $productUnit = $this->getProductUnit($productId, $unitId);

        return $productUnit->convertFromBase($baseQuantity);
    }

    /**
     * Get the conversion factor for a product's unit.
     */
    public function getConversionFactor(int $productId, int $unitId): float
    {
        $productUnit = $this->getProductUnit($productId, $unitId);

        return (float) $productUnit->to_base_factor;
    }

    /**
     * Get all units configured for a product, optionally filtered by allowed_for.
     */
    public function getProductUnits(int $productId, ?string $allowedFor = null): Collection
    {
        $query = ProductUnit::where('product_id', $productId)
            ->with('unit');

        if ($allowedFor === 'buy') {
            $query->whereIn('allowed_for', ['buy', 'both']);
        } elseif ($allowedFor === 'sell') {
            $query->whereIn('allowed_for', ['sell', 'both']);
        }

        return $query->get();
    }

    /**
     * Get the base unit for a product.
     */
    public function getBaseUnit(int $productId): ?ProductUnit
    {
        return ProductUnit::where('product_id', $productId)
            ->where('is_base', true)
            ->with('unit')
            ->first();
    }

    /**
     * Get a specific product-unit configuration.
     */
    private function getProductUnit(int $productId, int $unitId): ProductUnit
    {
        $productUnit = ProductUnit::where('product_id', $productId)
            ->where('unit_id', $unitId)
            ->first();

        if (! $productUnit) {
            throw new InvalidArgumentException(
                "Unit ID {$unitId} is not configured for product ID {$productId}."
            );
        }

        return $productUnit;
    }
}
