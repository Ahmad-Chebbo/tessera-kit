<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\Enums\TransferStatus;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'from_location_id',
        'to_location_id',
        'status',
        'notes',
        'created_by',
        'approved_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransferStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
