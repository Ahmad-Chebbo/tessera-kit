<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default currency
    |--------------------------------------------------------------------------
    |
    | Used when nobody is signed in or the company has no currency of its own.
    |
    */

    'default' => env('CURRENCY_DEFAULT', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Currencies
    |--------------------------------------------------------------------------
    |
    | The currencies companies can choose, keyed by ISO code. Publish this file to add or change currencies:
    | php artisan vendor:publish --tag=tessera-company-config
    |
    */

    'currencies' => [
        'USD' => [
            'name' => 'US Dollar',
            'name_ar' => 'دولار أمريكي',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'EUR' => [
            'name' => 'Euro',
            'name_ar' => 'يورو',
            'symbol' => '€',
            'symbol_position' => 'before',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'GBP' => [
            'name' => 'British Pound',
            'name_ar' => 'جنيه إسترليني',
            'symbol' => '£',
            'symbol_position' => 'before',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'LBP' => [
            'name' => 'Lebanese Pound',
            'name_ar' => 'ليرة لبنانية',
            'symbol' => 'ل.ل',
            'symbol_position' => 'after',
            'decimals' => 0,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'AED' => [
            'name' => 'UAE Dirham',
            'name_ar' => 'درهم إماراتي',
            'symbol' => 'د.إ',
            'symbol_position' => 'after',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'SAR' => [
            'name' => 'Saudi Riyal',
            'name_ar' => 'ريال سعودي',
            'symbol' => 'ر.س',
            'symbol_position' => 'after',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'QAR' => [
            'name' => 'Qatari Riyal',
            'name_ar' => 'ريال قطري',
            'symbol' => 'ر.ق',
            'symbol_position' => 'after',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'KWD' => [
            'name' => 'Kuwaiti Dinar',
            'name_ar' => 'دينار كويتي',
            'symbol' => 'د.ك',
            'symbol_position' => 'after',
            'decimals' => 3,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'JOD' => [
            'name' => 'Jordanian Dinar',
            'name_ar' => 'دينار أردني',
            'symbol' => 'د.أ',
            'symbol_position' => 'after',
            'decimals' => 3,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'EGP' => [
            'name' => 'Egyptian Pound',
            'name_ar' => 'جنيه مصري',
            'symbol' => 'ج.م',
            'symbol_position' => 'after',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
        'TRY' => [
            'name' => 'Turkish Lira',
            'name_ar' => 'ليرة تركية',
            'symbol' => '₺',
            'symbol_position' => 'before',
            'decimals' => 2,
            'decimal_separator' => '.',
            'thousands_separator' => ',',
        ],
    ],

];
