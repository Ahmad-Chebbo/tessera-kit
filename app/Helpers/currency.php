<?php

use App\Modules\Company\Services\CurrencyService;

if (! function_exists('format_currency')) {
    /**
     * Format a number as currency using the current user's company currency.
     */
    function format_currency(float|int $amount, ?string $currencyCode = null): string
    {
        return app(CurrencyService::class)->format($amount, $currencyCode);
    }
}

if (! function_exists('currency_symbol')) {
    /**
     * Get the currency symbol for the given or current currency.
     */
    function currency_symbol(?string $currencyCode = null): string
    {
        return app(CurrencyService::class)->getSymbol($currencyCode);
    }
}

if (! function_exists('currency_code')) {
    /**
     * Get the current currency code.
     */
    function currency_code(): string
    {
        return app(CurrencyService::class)->getCurrencyCode();
    }
}

if (! function_exists('convert_currency')) {
    /**
     * Convert amount from one currency to another.
     */
    function convert_currency(float $amount, string $fromCurrency, ?string $toCurrency = null): ?float
    {
        return app(CurrencyService::class)->convert($amount, $fromCurrency, $toCurrency);
    }
}

if (! function_exists('convert_and_format_currency')) {
    /**
     * Convert amount and format it.
     */
    function convert_and_format_currency(float $amount, string $fromCurrency, ?string $toCurrency = null): ?string
    {
        return app(CurrencyService::class)->convertAndFormat($amount, $fromCurrency, $toCurrency);
    }
}

if (! function_exists('can_convert_currency')) {
    /**
     * Check if conversion is possible between two currencies.
     */
    function can_convert_currency(string $fromCurrency, string $toCurrency): bool
    {
        return app(CurrencyService::class)->canConvert($fromCurrency, $toCurrency);
    }
}
