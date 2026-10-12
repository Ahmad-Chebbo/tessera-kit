<?php

namespace App\Modules\Pos\Models;

use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldOrder extends Model
{
    protected $fillable = [
        'tenant_id',
        'branch_id',
        'cashier_id',
        'name',
        'items',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'total' => 'decimal:2',
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

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
