<?php

namespace App\Modules\Discounts\Engine;

use Carbon\CarbonImmutable;
use Tessera\Core\Pricing\CartLine;

class EvaluationContext
{
    public readonly CustomerHistory $history;

    /**
     * @param  list<CartLine>  $lines
     */
    public function __construct(
        public readonly int $companyId,
        public readonly int $branchId,
        public readonly ?int $customerId,
        public readonly ?int $customerGroupId,
        public readonly string $paymentType,
        public readonly CarbonImmutable $now,
        public readonly array $lines,
        public readonly ?string $couponCode = null,
    ) {
        $this->history = new CustomerHistory($companyId, $customerId, $now);
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(fn (CartLine $line): float => $line->total(), $this->lines)), 2);
    }

    public function quantity(): float
    {
        return array_sum(array_map(fn (CartLine $line): float => $line->quantity, $this->lines));
    }
}
