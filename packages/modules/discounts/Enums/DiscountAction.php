<?php

namespace App\Modules\Discounts\Enums;

enum DiscountAction: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
    case Price = 'price';
    case BuyXGetY = 'buy_x_get_y';
    case BuyGetItem = 'buy_get_item';

    public function label(): string
    {
        return match ($this) {
            self::Percent => 'Percent off',
            self::Fixed => 'Fixed amount off',
            self::Price => 'Special price',
            self::BuyXGetY => 'Buy X get Y',
            self::BuyGetItem => 'Buy items, get other items',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
