<?php

namespace App\Modules\Discounts\Models;

use App\Modules\Discounts\Database\Factories\DiscountRuleFactory;
use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[UseFactory(DiscountRuleFactory::class)]
class DiscountRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
        'priority',
        'stackable',
        'starts_at',
        'ends_at',
        'conditions',
        'expression',
        'action_type',
        'action_value',
        'action_config',
        'coupon_code',
        'usage_limit',
        'per_customer_limit',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stackable' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'conditions' => 'array',
            'action_type' => DiscountAction::class,
            'action_value' => 'float',
            'action_config' => 'array',
            'usage_limit' => 'integer',
            'per_customer_limit' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function applications(): HasMany
    {
        return $this->hasMany(DiscountApplication::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeRunningAt(Builder $query, Carbon|\DateTimeInterface $moment): void
    {
        $query->where('is_active', true)
            ->where(fn (Builder $inner) => $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $moment))
            ->where(fn (Builder $inner) => $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $moment));
    }

    /**
     * Setting for the action, with a default when the rule does not define it.
     */
    public function config(string $key, mixed $default = null): mixed
    {
        return ($this->action_config ?? [])[$key] ?? $default;
    }
}
