<?php

namespace App\Modules\Expenses\Services;

use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Models\ExpenseCategory;

class ExpenseCategoryDefaults
{
    /**
     * @var array<string, ExpenseType>
     */
    public const CATEGORIES = [
        'Salaries' => ExpenseType::Salaries,
        'Rent' => ExpenseType::Operating,
        'Utilities' => ExpenseType::Operating,
        'Marketing' => ExpenseType::Operating,
        'Maintenance' => ExpenseType::Operating,
        'Taxes & fees' => ExpenseType::Tax,
        'Stock purchases' => ExpenseType::Purchases,
        'Equipment & assets' => ExpenseType::Assets,
        'Other' => ExpenseType::Other,
    ];

    /**
     * Gives a tenant a starting set of categories the first time it uses expenses.
     */
    public function ensure(int $tenantId): void
    {
        if (ExpenseCategory::query()->where('tenant_id', $tenantId)->exists()) {
            return;
        }

        foreach (self::CATEGORIES as $name => $type) {
            ExpenseCategory::create([
                'tenant_id' => $tenantId,
                'name' => $name,
                'type' => $type,
                'affects_profit' => $type->affectsProfitByDefault(),
                'is_active' => true,
            ]);
        }
    }
}
