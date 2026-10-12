<?php

namespace Tessera\Core\Contracts\Defaults;

use App\Modules\Team\Models\User;
use Tessera\Core\Contracts\CartPricing;
use Tessera\Core\Pricing\DiscountResult;
use Tessera\Core\Pricing\PricingContext;

class NoCartPricing implements CartPricing
{
    public function evaluate(PricingContext $context): DiscountResult
    {
        return new DiscountResult($context->lines, $context->couponCode ? DiscountResult::COUPON_UNKNOWN : DiscountResult::COUPON_NONE);
    }

    public function record(int $orderId, DiscountResult $result, array $itemIds): void {}

    public function preview(User $user, array $items, ?int $customerId = null, ?string $couponCode = null, string $paymentType = 'cash'): array
    {
        return ['total' => 0.0, 'coupon_status' => DiscountResult::COUPON_NONE, 'suggestions' => [], 'lines' => [], 'subtotal' => 0.0];
    }
}
