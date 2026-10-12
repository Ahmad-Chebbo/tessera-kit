<?php

namespace App\Modules\Shifts\Models;

use App\Modules\Shifts\Database\Factories\ShiftAssignmentFactory;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(ShiftAssignmentFactory::class)]
class ShiftAssignment extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'user_id', 'shift_template_id', 'shift_cycle_id', 'date'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ShiftCycle::class, 'shift_cycle_id');
    }
}
