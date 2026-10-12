<?php

namespace App\Modules\Shifts\Models;

use App\Modules\Shifts\Database\Factories\ShiftTemplateFactory;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ShiftTemplateFactory::class)]
class ShiftTemplate extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Length of the shift; a shift ending before it starts runs past midnight.
     */
    public function durationMinutes(): int
    {
        [$startHour, $startMinute] = array_map('intval', explode(':', $this->starts_at));
        [$endHour, $endMinute] = array_map('intval', explode(':', $this->ends_at));

        $minutes = ($endHour * 60 + $endMinute) - ($startHour * 60 + $startMinute);

        return $minutes > 0 ? $minutes : $minutes + 24 * 60;
    }
}
