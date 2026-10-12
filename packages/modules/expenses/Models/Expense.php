<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Expenses\Casts\DateOnly;
use App\Modules\Expenses\Database\Factories\ExpenseFactory;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[UseFactory(ExpenseFactory::class)]
class Expense extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const RECEIPT_DISK = 'local';

    protected $fillable = [
        'company_id',
        'branch_id',
        'expense_category_id',
        'created_by',
        'employee_id',
        'purchase_id',
        'recurring_expense_id',
        'amount',
        'description',
        'expense_date',
        'spread_months',
        'reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expense_date' => DateOnly::class,
            'spread_months' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('receipt')->singleFile()->useDisk(self::RECEIPT_DISK);
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function recurring(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class, 'recurring_expense_id');
    }

    /**
     * An expense spread over several months counts a little each day instead of all on its date.
     */
    public function isSpread(): bool
    {
        return ($this->spread_months ?? 0) > 1;
    }

    /**
     * The last day an expense still counts, for spread expenses.
     */
    public function lastDay(): CarbonImmutable
    {
        $start = CarbonImmutable::parse($this->expense_date->toDateString());

        return $this->isSpread() ? $start->addMonthsNoOverflow($this->spread_months)->subDay() : $start;
    }
}
