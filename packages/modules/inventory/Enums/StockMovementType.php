<?php

namespace App\Modules\Inventory\Enums;

enum StockMovementType: string
{
    case Purchase = 'purchase';
    case TransferIn = 'transfer_in';
    case TransferOut = 'transfer_out';
    case Sale = 'sale';
    case Adjustment = 'adjustment';

    public function isIncoming(): bool
    {
        return in_array($this, [self::Purchase, self::TransferIn, self::Adjustment]);
    }
}
