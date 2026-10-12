<?php

namespace App\Modules\Sales\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Customers\Models\Customer;
use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'pos_shift_id',
        'order_number',
        'offline_id',
        'subtotal',
        'total',
        'discount_type',
        'discount_value',
        'discount_amount',
        'rules_discount_amount',
        'status',
        'cashier_id',
        'customer_id',
        'payment_type',
        'debt_status',
        'debt_paid_at',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'rules_discount_amount' => 'decimal:2',
            'status' => OrderStatus::class,
            'debt_paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function posShift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
