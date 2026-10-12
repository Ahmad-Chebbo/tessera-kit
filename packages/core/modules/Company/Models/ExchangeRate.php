<?php

namespace App\Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    protected $fillable = [
        'company_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the exchange rate between two currencies for a company.
     */
    public static function getRate(int $companyId, string $fromCurrency, string $toCurrency): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = "exchange_rate:{$companyId}:{$fromCurrency}:{$toCurrency}";

        return Cache::remember($cacheKey, 3600, function () use ($companyId, $fromCurrency, $toCurrency) {
            // Try direct rate
            $rate = self::where('company_id', $companyId)
                ->where('from_currency', $fromCurrency)
                ->where('to_currency', $toCurrency)
                ->where('is_active', true)
                ->value('rate');

            if ($rate !== null) {
                return (float) $rate;
            }

            // Try inverse rate
            $inverseRate = self::where('company_id', $companyId)
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
    public static function convert(int $companyId, float $amount, string $fromCurrency, string $toCurrency): ?float
    {
        $rate = self::getRate($companyId, $fromCurrency, $toCurrency);

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
            Cache::forget("exchange_rate:{$rate->company_id}:{$rate->from_currency}:{$rate->to_currency}");
            Cache::forget("exchange_rate:{$rate->company_id}:{$rate->to_currency}:{$rate->from_currency}");
        });

        static::deleted(function (ExchangeRate $rate) {
            Cache::forget("exchange_rate:{$rate->company_id}:{$rate->from_currency}:{$rate->to_currency}");
            Cache::forget("exchange_rate:{$rate->company_id}:{$rate->to_currency}:{$rate->from_currency}");
        });
    }
}
