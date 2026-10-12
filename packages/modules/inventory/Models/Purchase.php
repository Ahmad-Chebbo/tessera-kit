<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    protected $fillable = [
        'company_id',
        'supplier_id',
        'location_id',
        'created_by',
        'currency',
        'exchange_rate',
        'reference_number',
        'status',
        'subtotal',
        'tax',
        'total',
        'notes',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
            'received_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isReceived(): bool
    {
        return $this->status === 'received';
    }
}
