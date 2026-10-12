<?php

namespace Tessera\Core\Pricing;

use Carbon\CarbonImmutable;

/**
 * Everything the pricing rules need to know about a sale in progress.
 */
final readonly class PricingContext
{
    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public int $companyId,
        public int $branchId,
        public ?int $customerId,
        public ?int $customerGroupId,
        public string $paymentType,
        public CarbonImmutable $now,
        public array $lines,
        public ?string $couponCode = null,
    ) {}
}
