<?php

namespace App\Modules\Catalog\Enums;

enum AllowedFor: string
{
    case Buy = 'buy';
    case Sell = 'sell';
    case Both = 'both';

    public function allowsBuying(): bool
    {
        return $this === self::Buy || $this === self::Both;
    }

    public function allowsSelling(): bool
    {
        return $this === self::Sell || $this === self::Both;
    }
}
