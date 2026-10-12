<?php

namespace App\Modules\Shifts\Models;

use App\Modules\Shifts\Database\Factories\ShiftCycleFactory;
use App\Modules\Team\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ShiftCycleFactory::class)]
class ShiftCycle extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'user_id', 'shift_template_id', 'weekdays', 'starts_on', 'ends_on'];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Create the missing assignments for this cycle between two dates, inside the cycle's own range.
     * Weekdays follow Carbon: 0 is Sunday and 6 is Saturday.
     */
    public function generate(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = $from->copy()->startOfDay()->max($this->starts_on->copy()->startOfDay());
        $end = $this->ends_on ? $to->copy()->startOfDay()->min($this->ends_on->copy()->startOfDay()) : $to->copy()->startOfDay();
        $created = 0;

        for ($day = $start; $day->lte($end); $day = $day->copy()->addDay()) {
            if (! in_array($day->dayOfWeek, $this->weekdays, true)) {
                continue;
            }

            $assignment = ShiftAssignment::firstOrCreate(
                ['user_id' => $this->user_id, 'shift_template_id' => $this->shift_template_id, 'date' => $day->toDateString()],
                ['company_id' => $this->company_id, 'shift_cycle_id' => $this->id],
            );

            $created += $assignment->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }
}
