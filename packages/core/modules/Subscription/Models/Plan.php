<?php

namespace App\Modules\Subscription\Models;

use App\Modules\Subscription\Database\Factories\PlanFactory;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Services\Entitlements;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(PlanFactory::class)]
class Plan extends Model
{
    use HasFactory;

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const LIFETIME = 'lifetime';

    /**
     * @var list<string>
     */
    public const INTERVALS = [self::MONTHLY, self::YEARLY, self::LIFETIME];

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'currency',
        'billing_interval',
        'trial_days',
        'modules',
        'max_users',
        'max_branches',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'modules' => 'array',
            'trial_days' => 'integer',
            'max_users' => 'integer',
            'max_branches' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function includes(Module|string $module): bool
    {
        return in_array($module instanceof Module ? $module->value : $module, $this->modules ?? [], true);
    }

    protected static function booted(): void
    {
        static::saved(fn () => app(Entitlements::class)->flush());
        static::deleted(fn () => app(Entitlements::class)->flush());
    }
}
