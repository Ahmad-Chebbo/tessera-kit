<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Attendance\Database\Factories\AttendanceRecordFactory;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(AttendanceRecordFactory::class)]
class AttendanceRecord extends Model
{
    use HasFactory;

    public const SELF = 'self';

    public const MANAGER = 'manager';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'open_user_id',
        'shift_template_id',
        'created_by',
        'work_date',
        'check_in_at',
        'check_out_at',
        'late_minutes',
        'early_leave_minutes',
        'worked_minutes',
        'source',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'worked_minutes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class);
    }

    public function isOpen(): bool
    {
        return $this->check_out_at === null;
    }
}
