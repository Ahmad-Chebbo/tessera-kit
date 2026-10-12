<?php

namespace Tessera\Core;

use Illuminate\Support\ServiceProvider;
use Tessera\Core\Contracts\CartPricing;
use Tessera\Core\Contracts\CustomerActivity;
use Tessera\Core\Contracts\Defaults\NoCartPricing;
use Tessera\Core\Contracts\Defaults\NoCustomerActivity;
use Tessera\Core\Contracts\Defaults\NoLocationUsage;
use Tessera\Core\Contracts\Defaults\NoNetProfit;
use Tessera\Core\Contracts\Defaults\NoPurchaseLookup;
use Tessera\Core\Contracts\Defaults\NoRevenueSource;
use Tessera\Core\Contracts\LocationUsage;
use Tessera\Core\Contracts\NetProfit;
use Tessera\Core\Contracts\PurchaseLookup;
use Tessera\Core\Contracts\RevenueSource;

/**
 * Gives the contracts that feature modules share a default that does nothing, so a module works when the module that
 * would fill the contract is not installed. A module that does fill it binds its own class, which wins.
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public const DEFAULTS = [
        LocationUsage::class => NoLocationUsage::class,
        CustomerActivity::class => NoCustomerActivity::class,
        RevenueSource::class => NoRevenueSource::class,
        CartPricing::class => NoCartPricing::class,
        NetProfit::class => NoNetProfit::class,
        PurchaseLookup::class => NoPurchaseLookup::class,
    ];

    public function register(): void
    {
        foreach (self::DEFAULTS as $contract => $default) {
            $this->app->bindIf($contract, $default);
        }

        config(['tessera.contracts' => array_values(array_unique([...config('tessera.contracts', []), ...array_keys(self::DEFAULTS)]))]);
    }
}
