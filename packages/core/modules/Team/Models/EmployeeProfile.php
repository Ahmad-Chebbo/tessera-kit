<?php

namespace App\Modules\Team\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Team\Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(EmployeeProfileFactory::class)]
class EmployeeProfile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    public const EDITABLE = [
        'employee_number',
        'phone',
        'job_title',
        'hire_date',
        'birth_date',
        'national_id',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'notes',
    ];

    protected $fillable = [
        'user_id',
        'company_id',
        ...self::EDITABLE,
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'birth_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
