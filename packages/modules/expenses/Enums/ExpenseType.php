<?php

namespace App\Modules\Expenses\Enums;

enum ExpenseType: string
{
    case Operating = 'operating';
    case Salaries = 'salaries';
    case Purchases = 'purchases';
    case Assets = 'assets';
    case Tax = 'tax';
    case Other = 'other';

    /**
     * Whether categories of this type reduce profit unless the tenant says otherwise. Purchases are left out because
     * stock is already costed into the products sold, and assets are capital spending.
     */
    public function affectsProfitByDefault(): bool
    {
        return ! in_array($this, [self::Purchases, self::Assets], true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
