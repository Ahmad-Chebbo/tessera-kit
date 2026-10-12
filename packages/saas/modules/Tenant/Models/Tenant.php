<?php

namespace App\Modules\Tenant\Models;

use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[UseFactory(TenantFactory::class)]
class Tenant extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'currency',
        'locale',
        'tax_rate',
        'timezone',
        'receipt_header',
        'receipt_footer',
        'address',
        'phone',
        'email',
        'is_active',
        'require_pos_shift',
    ];

    protected $with = ['media'];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'require_pos_shift' => 'boolean',
        ];
    }

    /**
     * Get the URL of the product's main image (or fallback if none).
     */
    public function getLogoUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('logo');
        if ($media) {
            return $media->getUrl();
        }

        // Return fallback URL as defined in media collection
        return '/default/product.png';
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo')->useFallbackUrl('/default/product.png')->singleFile();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
