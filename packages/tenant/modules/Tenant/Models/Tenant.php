<?php

namespace App\Modules\Tenant\Models;

use App\Modules\Team\Models\User;
use App\Modules\Tenant\Database\Factories\TenantFactory;
use App\Modules\Tenant\Enums\LocationType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
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

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'require_pos_shift' => 'boolean',
        ];
    }

    /**
     * The tenant of a project with a single business: the first one. When there is none yet it is made with the app's
     * name, a main branch and the stock location of that branch, like every tenant a platform admin creates.
     */
    public static function default(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function (): self {
            $tenant = static::create([
                'name' => (string) config('app.name'),
                'currency' => (string) config('currencies.default', 'USD'),
                'locale' => app()->getLocale(),
                'timezone' => (string) config('app.timezone'),
            ]);

            $branch = Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Branch', 'is_active' => true]);

            Location::create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Main Branch', 'type' => LocationType::Branch, 'is_active' => true]);

            return $tenant;
        });
    }

    /**
     * The first branch of the tenant, where people without a branch of their own are placed.
     */
    public function mainBranch(): ?Branch
    {
        return $this->branches()->orderBy('id')->first();
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
