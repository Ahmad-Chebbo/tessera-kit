<?php

namespace App\Modules\Leave\Models;

use App\Modules\Leave\Database\Factories\LeaveRequestFactory;
use App\Modules\Leave\Enums\LeaveStatus;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(LeaveRequestFactory::class)]
class LeaveRequest extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'leave_type_id', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'days' => 'float',
            'status' => LeaveStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
