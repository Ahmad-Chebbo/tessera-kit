<?php

namespace App\Modules\Attendance\Models;

use App\Modules\Attendance\Database\Factories\AttendanceExcuseFactory;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(AttendanceExcuseFactory::class)]
class AttendanceExcuse extends Model
{
    use HasFactory;

    protected $fillable = ['company_id', 'user_id', 'created_by', 'date', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
