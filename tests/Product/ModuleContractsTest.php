<?php

use App\Modules\Discounts\Services\RuleCartPricing;
use App\Modules\Expenses\Services\ProfitStatement;
use App\Modules\Inventory\Services\ReceivedPurchaseLookup;
use App\Modules\Inventory\Services\StockLocationUsage;
use App\Modules\Sales\Services\OrderCustomerActivity;
use App\Modules\Sales\Services\OrderRevenueSource;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
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
use Tessera\Core\Pricing\CartLine;
use Tessera\Core\Pricing\DiscountResult;
use Tessera\Core\Pricing\PricingContext;

describe('with the providing module installed', function () {
    it('binds the real implementation', function () {
        expect(app(LocationUsage::class))->toBeInstanceOf(StockLocationUsage::class)
            ->and(app(PurchaseLookup::class))->toBeInstanceOf(ReceivedPurchaseLookup::class)
            ->and(app(CustomerActivity::class))->toBeInstanceOf(OrderCustomerActivity::class)
            ->and(app(RevenueSource::class))->toBeInstanceOf(OrderRevenueSource::class)
            ->and(app(NetProfit::class))->toBeInstanceOf(ProfitStatement::class)
            ->and(app(CartPricing::class))->toBeInstanceOf(RuleCartPricing::class);
    });
});

describe('without the providing module', function () {
    it('finds no location in use and no purchase to link', function () {
        expect((new NoLocationUsage)->inUse(1))->toBeFalse()
            ->and((new NoPurchaseLookup)->available())->toBeFalse()
            ->and((new NoPurchaseLookup)->linkable(1, [2], 3))->toBe([]);
    });

    it('shows no customer activity', function () {
        $activity = new NoCustomerActivity;

        expect($activity->available())->toBeFalse()
            ->and($activity->summary(1))->toBe(['orders' => 0, 'spent' => 0.0, 'debt' => 0.0, 'paid_debt' => 0.0])
            ->and($activity->totals([1, 2]))->toBe([])
            ->and($activity->orders(1)->total())->toBe(0);
    });

    it('has no revenue and no net profit statement', function () {
        $from = CarbonImmutable::parse('2026-10-01');
        $to = CarbonImmutable::parse('2026-10-31');

        expect((new NoRevenueSource)->byMonth(1, $from, $to))->toBe([])
            ->and((new NoNetProfit)->available())->toBeFalse()
            ->and((new NoNetProfit)->forPeriod(1, $from, $to))->toBe(['months' => [], 'totals' => []]);
    });

    it('prices a cart without discounts and rejects any coupon', function () {
        $line = new CartLine(0, 5, null, 2, 2, 10);
        $context = fn (?string $coupon) => new PricingContext(1, 1, null, null, 'cash', CarbonImmutable::now(), [$line], $coupon);

        $plain = (new NoCartPricing)->evaluate($context(null));
        $withCoupon = (new NoCartPricing)->evaluate($context('SAVE10'));

        expect($plain->total())->toBe(0.0)
            ->and($plain->couponStatus)->toBe(DiscountResult::COUPON_NONE)
            ->and($withCoupon->couponStatus)->toBe(DiscountResult::COUPON_UNKNOWN)
            ->and((new NoCartPricing)->preview(User::factory()->make(), [])['total'])->toBe(0.0);
    });
});

describe('shared props', function () {
    it('tells the pages which modules are enabled', function () {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.edit'))
            ->assertInertia(fn ($page) => $page->has('enabledModules')->where('enabledModules', fn ($modules) => collect($modules)->contains('sales') && collect($modules)->contains('team')));
    });
});
