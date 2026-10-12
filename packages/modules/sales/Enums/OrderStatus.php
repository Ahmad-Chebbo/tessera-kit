<?php

namespace App\Modules\Sales\Enums;

enum OrderStatus: string
{
    case Completed = 'completed';
    case Voided = 'voided';
    case Debt = 'debt';
}
