<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\AllowedFor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUnit extends Model
{
    protected $fillable = [
        'product_id',
        'unit_id',
        'to_base_factor',
        'allowed_for',
        'is_base',
    ];

    protected function casts(): array
    {
        return [
            'to_base_factor' => 'decimal:4',
            'allowed_for' => AllowedFor::class,
            'is_base' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Convert a quantity in this unit to the product's base unit.
     */
    public function convertToBase(float $quantity): float
    {
        return $quantity * (float) $this->to_base_factor;
    }

    /**
     * Convert a base-unit quantity to this unit.
     */
    public function convertFromBase(float $baseQuantity): float
    {
        if ((float) $this->to_base_factor === 0.0) {
            return 0;
        }

        return $baseQuantity / (float) $this->to_base_factor;
    }
}
