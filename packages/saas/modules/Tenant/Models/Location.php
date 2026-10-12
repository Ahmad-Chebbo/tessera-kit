<?php

namespace App\Modules\Tenant\Models;

use App\Modules\Tenant\Database\Factories\LocationFactory;
use App\Modules\Tenant\Enums\LocationType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LocationFactory::class)]
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'name',
        'type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function isWarehouse(): bool
    {
        return $this->type === LocationType::Warehouse;
    }

    public function isBranch(): bool
    {
        return $this->type === LocationType::Branch;
    }
}
