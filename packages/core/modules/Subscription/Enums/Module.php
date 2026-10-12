<?php

namespace App\Modules\Subscription\Enums;

use Tessera\Engine\ModuleRegistry;

/**
 * Names of the plan features that ship with the platform. The features themselves (label and permissions) are
 * declared by the modules and read from the module registry; this enum only gives the shipped keys a name.
 */
enum Module: string
{
    case Pos = 'pos';
    case Catalog = 'catalog';
    case Inventory = 'inventory';
    case Customers = 'customers';
    case Expenses = 'expenses';
    case Reports = 'reports';
    case Branches = 'branches';
    case Shifts = 'shifts';
    case Attendance = 'attendance';
    case Activity = 'activity';
    case Roles = 'roles';
    case Discounts = 'discounts';
    case Exports = 'exports';

    public function label(): string
    {
        return app(ModuleRegistry::class)->features()[$this->value]->label ?? ucfirst($this->value);
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return app(ModuleRegistry::class)->features()[$this->value]->permissions ?? [];
    }

    /**
     * Keys of every plan feature the enabled modules declare.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(app(ModuleRegistry::class)->features());
    }
}
