<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'abbreviation',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    /**
     * Scope to only active units.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to only global (system) units (tenant_id is null).
     */
    public function scopeGlobal($query)
    {
        return $query->whereNull('tenant_id');
    }

    /**
     * Scope to units available for a tenant (global + tenant-specific).
     */
    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('tenant_id')
                ->orWhere('tenant_id', $tenantId);
        });
    }

    /**
     * Check if this is a global (system) unit.
     */
    public function isGlobal(): bool
    {
        return $this->tenant_id === null;
    }
}
