<?php

namespace App\Modules\Discounts\Services;

use App\Modules\Discounts\Engine\DiscountEngine;
use App\Modules\Discounts\Engine\EvaluationContext;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Team\Models\User;
use Tessera\Core\Contracts\CartPricing;
use Tessera\Core\Pricing\DiscountResult;
use Tessera\Core\Pricing\PricingContext;

class RuleCartPricing implements CartPricing
{
    public function __construct(private readonly DiscountEngine $engine, private readonly DiscountPreview $preview) {}

    public function evaluate(PricingContext $context): DiscountResult
    {
        return $this->engine->evaluate(new EvaluationContext(
            tenantId: $context->tenantId,
            branchId: $context->branchId,
            customerId: $context->customerId,
            customerGroupId: $context->customerGroupId,
            paymentType: $context->paymentType,
            now: $context->now,
            lines: $context->lines,
            couponCode: $context->couponCode,
        ));
    }

    public function record(int $orderId, DiscountResult $result, array $itemIds): void
    {
        foreach ($result->applications() as $application) {
            DiscountApplication::create([
                'order_id' => $orderId,
                'order_item_id' => $itemIds[$application['line_key']],
                'discount_rule_id' => $application['rule_id'],
                'rule_name' => $application['rule_name'],
                'coupon_code' => $application['details']['coupon'] ?? null,
                'amount' => $application['amount'],
                'details' => $application['details'],
            ]);
        }
    }

    public function preview(User $user, array $items, ?int $customerId = null, ?string $couponCode = null, string $paymentType = 'cash'): array
    {
        return $this->preview->run($user, $items, $customerId, $couponCode, $paymentType);
    }
}
