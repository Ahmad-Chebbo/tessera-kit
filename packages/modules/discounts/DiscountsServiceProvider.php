<?php

namespace App\Modules\Discounts;

use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Services\RuleCartPricing;
use App\Modules\Sales\Models\Order;
use Tessera\Core\Contracts\CartPricing;
use Tessera\Engine\MenuItem;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;
use Tessera\Engine\PlanFeature;

/**
 * Discounts module.
 */
class DiscountsServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'discounts',
            name: 'Discounts',
            requires: ['catalog', 'tenant', 'customers', 'sales', 'team'],
        );
    }

    public function features(): array
    {
        return [
            new PlanFeature('discounts', 'Discounts & promotions', ['manage discounts'], order: 120),
        ];
    }

    public function permissionGroups(): array
    {
        return [
            'sales' => ['manage discounts'],
        ];
    }

    public function defaultRolePermissions(): array
    {
        return [
            'Owner' => ['manage discounts'],
            'Manager' => ['manage discounts'],
        ];
    }

    public function menu(): array
    {
        return [
            new MenuItem('sales', 'discounts.nav', 'discounts.index', 'percent', 'manage discounts', ['discounts.*'], 40),
        ];
    }

    protected function bindings(): void
    {
        $this->app->bind(CartPricing::class, RuleCartPricing::class);
    }

    public function boot(): void
    {
        parent::boot();

        Order::resolveRelationUsing('discountApplications', fn (Order $order) => $order->hasMany(DiscountApplication::class));
    }
}
