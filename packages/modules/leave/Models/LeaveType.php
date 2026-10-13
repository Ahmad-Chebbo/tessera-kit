<?php

namespace App\Modules\Leave\Models;

use App\Modules\Leave\Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(LeaveTypeFactory::class)]
class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = ['tenant_id', 'name', 'days_per_year', 'is_paid', 'is_active'];

    protected function casts(): array
    {
        return [
            'days_per_year' => 'float',
            'is_paid' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }
}
