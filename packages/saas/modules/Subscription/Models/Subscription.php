<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Subscription\Database\Factories\SubscriptionFactory;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Services\Entitlements;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(SubscriptionFactory::class)]
class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'starts_at',
        'trial_ends_at',
        'ends_at',
        'canceled_at',
        'module_overrides',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'module_overrides' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Whether the tenant may use the platform right now.
     */
    public function isUsable(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            SubscriptionStatus::Active => $this->ends_at === null || $this->ends_at->isFuture(),
            SubscriptionStatus::Canceled => $this->ends_at !== null && $this->ends_at->isFuture(),
            default => false,
        };
    }

    /**
     * The status as the tenant experiences it: a lapsed trial or period reads as expired.
     */
    public function effectiveStatus(): SubscriptionStatus
    {
        if ($this->isUsable() || in_array($this->status, [SubscriptionStatus::PastDue, SubscriptionStatus::Canceled], true)) {
            return $this->status;
        }

        return SubscriptionStatus::Expired;
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(Entitlements::class)->flush());
        static::deleted(fn () => app(Entitlements::class)->flush());
    }
}
