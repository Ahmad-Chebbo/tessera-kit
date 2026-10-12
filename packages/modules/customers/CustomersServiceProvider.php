<?php

namespace App\Modules\Customers;

use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Customers and customer groups. Their order history comes from Sales through a contract.
 */
class CustomersServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'customers',
            name: 'Customers',
            requires: ['tenant'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('customers', 'Customers', ['view customers', 'create customers', 'edit customers', 'delete customers'], order: 40),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'sales' => ['view customers', 'create customers', 'edit customers', 'delete customers'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['view customers', 'create customers', 'edit customers', 'delete customers'],
            'Manager' => ['view customers', 'create customers', 'edit customers', 'delete customers'],
            'Cashier' => ['view customers', 'create customers'],
            'Stock Keeper' => ['view customers'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('sales', 'customers.title', 'customers.index', 'users-group', 'view customers', ['customers.*'], 20),
            new MenuItem('sales', 'customers.groupsNav', 'customer-groups.index', 'group', 'edit customers', ['customer-groups.*'], 30),
        ];
    }
}
