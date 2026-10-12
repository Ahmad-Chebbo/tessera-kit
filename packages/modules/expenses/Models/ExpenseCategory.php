<?php

namespace App\Modules\Expenses\Models;

use App\Modules\Company\Models\Company;
use App\Modules\Expenses\Database\Factories\ExpenseCategoryFactory;
use App\Modules\Expenses\Enums\ExpenseType;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(ExpenseCategoryFactory::class)]
class ExpenseCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'type',
        'affects_profit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ExpenseType::class,
            'affects_profit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
