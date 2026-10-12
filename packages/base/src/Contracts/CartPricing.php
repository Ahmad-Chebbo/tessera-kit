<?php

namespace Tessera\Core\Contracts;

use App\Modules\Team\Models\User;
use Tessera\Core\Pricing\DiscountResult;
use Tessera\Core\Pricing\PricingContext;

/**
 * Prices a cart with the company's discount rules. The Discounts module answers; without it nothing is discounted.
 */
interface CartPricing
{
    public function evaluate(PricingContext $context): DiscountResult;

    /**
     * Stores which rules applied to which saved order lines.
     *
     * @param  array<int|string, int>  $itemIds  Cart line key => order item id.
     */
    public function record(int $orderId, DiscountResult $result, array $itemIds): void;

    /**
     * The discounts a cart would get, for showing live in the till.
     *
     * @param  list<array{product_id: int, quantity: float|int|string}>  $items
     * @return array<string, mixed>
     */
    public function preview(User $user, array $items, ?int $customerId = null, ?string $couponCode = null, string $paymentType = 'cash'): array;
}
