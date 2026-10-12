<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;

class PricingService
{
    /**
     * Get the selling price for a product.
     * sale_price takes precedence over base_price.
     */
    public function getSellingPrice(Product $product): float
    {
        return (float) ($product->sale_price ?? $product->base_price);
    }

    /**
     * Calculate profit margin for a product.
     */
    public function getProfitMargin(Product $product): float
    {
        $sellingPrice = $this->getSellingPrice($product);

        if ($product->cost_price <= 0) {
            return 0;
        }

        return $sellingPrice - (float) $product->cost_price;
    }

    /**
     * Calculate profit percentage for a product.
     */
    public function getProfitPercentage(Product $product): float
    {
        if ((float) $product->cost_price <= 0) {
            return 0;
        }

        $margin = $this->getProfitMargin($product);

        return round(($margin / (float) $product->cost_price) * 100, 2);
    }
}
