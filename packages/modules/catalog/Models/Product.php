<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[UseFactory(ProductFactory::class)]
class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'barcode',
        'sku',
        'base_price',
        'sale_price',
        'cost_price',
        'currency',
        'unit',
        'description',
        'is_active',
        'unlimited_quantity',
    ];

    protected $with = ['media'];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
            'unlimited_quantity' => 'boolean',
        ];
    }

    // Append the image_url attribute when serializing
    protected $appends = ['image_url'];

    /**
     * Get the URL of the product's main image (or fallback if none).
     */
    public function getImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('images');
        if ($media) {
            return $media->getUrl();
        }

        // Return fallback URL as defined in media collection
        return '/default/product.png';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->useFallbackUrl('/default/product.png')->singleFile();
        $this->addMediaCollection('documents');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * All unit configurations for this product.
     */
    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * The base unit configuration for this product.
     */
    public function baseProductUnit(): HasOne
    {
        return $this->hasOne(ProductUnit::class)->where('is_base', true);
    }

    /**
     * Units allowed for buying (buy or both).
     */
    public function buyUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->whereIn('allowed_for', ['buy', 'both']);
    }

    /**
     * Units allowed for selling (sell or both).
     */
    public function sellUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class)->whereIn('allowed_for', ['sell', 'both']);
    }

    /**
     * Returns the effective selling price in the product's currency.
     * sale_price overrides base_price when set.
     */
    public function getSellingPrice(): float
    {
        return (float) ($this->sale_price ?? $this->base_price);
    }

    /**
     * Returns the selling price converted to the target currency.
     */
    public function getSellingPriceIn(string $targetCurrency): ?float
    {
        $price = $this->getSellingPrice();
        $productCurrency = $this->currency ?? 'USD';

        if ($productCurrency === $targetCurrency) {
            return $price;
        }

        return convert_currency($price, $productCurrency, $targetCurrency);
    }

    /**
     * Returns the cost price converted to the target currency.
     */
    public function getCostPriceIn(string $targetCurrency): ?float
    {
        $cost = (float) ($this->cost_price ?? 0);
        $productCurrency = $this->currency ?? 'USD';

        if ($productCurrency === $targetCurrency) {
            return $cost;
        }

        return convert_currency($cost, $productCurrency, $targetCurrency);
    }

    /**
     * Get the product's currency code.
     */
    public function getCurrency(): string
    {
        return $this->currency ?? 'USD';
    }
}
