<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Expenses\Casts\DateOnly;
use App\Modules\Expenses\Database\Factories\RecurringExpenseFactory;
use App\Modules\Expenses\Enums\RecurrenceFrequency;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(RecurringExpenseFactory::class)]
class RecurringExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'branch_id',
        'expense_category_id',
        'created_by',
        'employee_id',
        'amount',
        'description',
        'frequency',
        'starts_on',
        'ends_on',
        'next_run_on',
        'last_run_on',
        'runs_count',
        'spread_months',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'frequency' => RecurrenceFrequency::class,
            'starts_on' => DateOnly::class,
            'ends_on' => DateOnly::class,
            'next_run_on' => DateOnly::class,
            'last_run_on' => DateOnly::class,
            'runs_count' => 'integer',
            'spread_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
