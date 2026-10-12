<?php

namespace App\Modules\Company\Services;

use App\Modules\Company\Models\ExchangeRate;
use Illuminate\Support\Facades\Auth;

class CurrencyService
{
    protected string $currencyCode;

    protected ?array $currency;

    protected ?int $companyId = null;

    public function __construct(?string $currencyCode = null)
    {
        $this->setCurrency($currencyCode);
    }

    public function setCurrency(?string $currencyCode = null): self
    {
        if ($currencyCode === null) {
            $currencyCode = $this->getDefaultCurrency();
        }

        $this->currencyCode = $currencyCode;
        $this->currency = config("currencies.currencies.{$currencyCode}", config('currencies.currencies.USD'));

        return $this;
    }

    public function setCompanyId(?int $companyId): self
    {
        $this->companyId = $companyId;

        return $this;
    }

    protected function getCompanyId(): ?int
    {
        if ($this->companyId !== null) {
            return $this->companyId;
        }

        if (Auth::check()) {
            return Auth::user()->company_id;
        }

        return null;
    }

    protected function getDefaultCurrency(): string
    {
        if (Auth::check() && Auth::user()->company?->currency) {
            return Auth::user()->company->currency;
        }

        return config('currencies.default', 'USD');
    }

    public function format(float|int $amount, ?string $currencyCode = null): string
    {
        $currency = $this->currency;

        if ($currencyCode !== null && $currencyCode !== $this->currencyCode) {
            $currency = config("currencies.currencies.{$currencyCode}", $this->currency);
        }

        $formatted = number_format(
            $amount,
            $currency['decimals'],
            $currency['decimal_separator'],
            $currency['thousands_separator']
        );

        if ($currency['symbol_position'] === 'before') {
            return $currency['symbol'].$formatted;
        }

        return $formatted.' '.$currency['symbol'];
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convert(float $amount, string $fromCurrency, ?string $toCurrency = null): ?float
    {
        $toCurrency = $toCurrency ?? $this->currencyCode;

        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return null;
        }

        return ExchangeRate::convert($companyId, $amount, $fromCurrency, $toCurrency);
    }

    /**
     * Convert amount and format it.
     */
    public function convertAndFormat(float $amount, string $fromCurrency, ?string $toCurrency = null): ?string
    {
        $toCurrency = $toCurrency ?? $this->currencyCode;
        $converted = $this->convert($amount, $fromCurrency, $toCurrency);

        if ($converted === null) {
            return null;
        }

        return $this->format($converted, $toCurrency);
    }

    /**
     * Get exchange rate between two currencies.
     */
    public function getRate(string $fromCurrency, string $toCurrency): ?float
    {
        $companyId = $this->getCompanyId();

        if ($companyId === null) {
            return null;
        }

        return ExchangeRate::getRate($companyId, $fromCurrency, $toCurrency);
    }

    /**
     * Check if conversion is possible between two currencies.
     */
    public function canConvert(string $fromCurrency, string $toCurrency): bool
    {
        if ($fromCurrency === $toCurrency) {
            return true;
        }

        return $this->getRate($fromCurrency, $toCurrency) !== null;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function getCurrency(): array
    {
        return $this->currency;
    }

    public function getSymbol(?string $currencyCode = null): string
    {
        if ($currencyCode !== null && $currencyCode !== $this->currencyCode) {
            $currency = config("currencies.currencies.{$currencyCode}", $this->currency);

            return $currency['symbol'];
        }

        return $this->currency['symbol'];
    }

    public function getDecimals(?string $currencyCode = null): int
    {
        if ($currencyCode !== null && $currencyCode !== $this->currencyCode) {
            $currency = config("currencies.currencies.{$currencyCode}", $this->currency);

            return $currency['decimals'];
        }

        return $this->currency['decimals'];
    }

    public static function getAllCurrencies(): array
    {
        return config('currencies.currencies', []);
    }

    public static function getCurrencyOptions(): array
    {
        $currencies = [];
        $locale = app()->getLocale();

        foreach (config('currencies.currencies', []) as $code => $currency) {
            $name = $locale === 'ar' ? ($currency['name_ar'] ?? $currency['name']) : $currency['name'];
            $currencies[$code] = "{$name} ({$currency['symbol']})";
        }

        return $currencies;
    }

    /**
     * Get currency info by code.
     */
    public static function getCurrencyInfo(string $code): ?array
    {
        return config("currencies.currencies.{$code}");
    }
}
