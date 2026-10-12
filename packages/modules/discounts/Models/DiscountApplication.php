<?php

namespace App\Modules\Discounts\Models;

use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountApplication extends Model
{
    protected $fillable = [
        'order_id',
        'order_item_id',
        'discount_rule_id',
        'rule_name',
        'coupon_code',
        'amount',
        'details',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'details' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(DiscountRule::class, 'discount_rule_id');
    }
}
