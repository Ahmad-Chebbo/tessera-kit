<?php

namespace App\Modules\Tenant\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    protected $fillable = [
        'tenant_id',
        'from_currency',
        'to_currency',
        'rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the exchange rate between two currencies for a tenant.
     */
    public static function getRate(int $tenantId, string $fromCurrency, string $toCurrency): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:{$tenantId}:{$fromCurrency}:{$toCurrency}";

        return Cache::remember($cacheKey, 3600, function () use ($tenantId, $fromCurrency, $toCurrency) {
            // Try direct rate
            $rate = self::where('tenant_id', $tenantId)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->where('is_active', true)
                ->value('rate');

            if ($rate !== null) {
                return (float) $rate;
            }

            // Try inverse rate
            $inverseRate = self::where('tenant_id', $tenantId)
                ->where('from_currency', $toCurrency)
                ->where('to_currency', $fromCurrency)
                ->where('is_active', true)
                ->value('rate');

            if ($inverseRate !== null && $inverseRate > 0) {
                return 1 / (float) $inverseRate;
            }

            return null;
        });
    }

    /**
     * Convert an amount between currencies.
     */
    public static function convert(int $tenantId, float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $rate = self::getRate($tenantId, $fromCurrency, $toCurrency);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Clear the cache when rate is updated.
     */
    protected static function booted(): void
    {
        static::saved(function (ExchangeRate $rate) {
            Cache::forget("exchange_rate:{$rate->tenant_id}:{$rate->from_currency}:{$rate->to_currency}");
            Cache::forget("exchange_rate:{$rate->tenant_id}:{$rate->to_currency}:{$rate->from_currency}");
        });

        static::deleted(function (ExchangeRate $rate) {
            Cache::forget("exchange_rate:{$rate->tenant_id}:{$rate->from_currency}:{$rate->to_currency}");
            Cache::forget("exchange_rate:{$rate->tenant_id}:{$rate->to_currency}:{$rate->from_currency}");
        });
    }
}
