<?php

namespace App\Modules\Company\Models;

use App\Modules\Company\Database\Factories\LocationFactory;
use App\Modules\Company\Enums\LocationType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LocationFactory::class)]
class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
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

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
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
