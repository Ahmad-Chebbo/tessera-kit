<?php

namespace App\Modules\Pos\Models;

use App\Modules\Pos\Database\Factories\PosShiftFactory;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(PosShiftFactory::class)]
class PosShift extends Model
{
    use HasFactory;

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'user_id',
        'open_user_id',
        'status',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'counted_cash',
        'variance',
        'notes',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'variance' => 'decimal:2',
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isOpen(): bool
    {
        return $this->status === self::OPEN;
    }
}
