<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /**
     * Generate barcode image as PNG base64.
     */
    public function generatePNG(string $code, int $widthFactor = 2, int $height = 50): string
    {
        $generator = new BarcodeGeneratorPNG;
        $barcode = $generator->getBarcode($code, $generator::TYPE_CODE_128, $widthFactor, $height);

        return base64_encode($barcode);
    }

    /**
     * Generate barcode image as SVG.
     */
    public function generateSVG(string $code, int $widthFactor = 2, int $height = 50): string
    {
        $generator = new BarcodeGeneratorSVG;

        return $generator->getBarcode($code, $generator::TYPE_CODE_128, $widthFactor, $height);
    }

    /**
     * Generate barcode for a product using its SKU.
     */
    public function forProduct(Product $product, string $format = 'png'): string
    {
        $code = $product->sku ?? $product->id;

        return $format === 'svg'
            ? $this->generateSVG($code)
            : $this->generatePNG($code);
    }

    /**
     * Generate printable barcode labels HTML for multiple products.
     *
     * @param  array<Product>  $products
     */
    public function generateLabelsHTML(array $products, int $columns = 3): string
    {
        $html = '<div style="display: grid; grid-template-columns: repeat('.$columns.', 1fr); gap: 10px; padding: 10px;">';

        foreach ($products as $product) {
            $barcode = $this->generatePNG($product->sku ?? (string) $product->id);
            $html .= '
                <div style="border: 1px dashed #ccc; padding: 10px; text-align: center; page-break-inside: avoid;">
                    <img src="data:image/png;base64,'.$barcode.'" style="max-width: 100%;">
                    <div style="font-size: 10px; margin-top: 5px;">'.e($product->sku).'</div>
                    <div style="font-size: 12px; font-weight: bold;">'.e($product->name).'</div>
                    <div style="font-size: 11px;">$'.number_format($product->sale_price ?? $product->base_price, 2).'</div>
                </div>
            ';
        }

        $html .= '</div>';

        return $html;
    }
}
