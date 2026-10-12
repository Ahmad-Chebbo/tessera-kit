<?php

namespace Tessera\Core\Pricing;

class DiscountResult
{
    public const COUPON_NONE = 'none';

    public const COUPON_APPLIED = 'applied';

    public const COUPON_UNKNOWN = 'unknown';

    public const COUPON_EXHAUSTED = 'exhausted';

    public const COUPON_NOT_APPLICABLE = 'not_applicable';

    /**
     * @param  list<CartLine>  $lines
     * @param  list<array{rule_id: int, rule_name: string, quantity: float, products: list<array{id: int, name: string}>}>  $suggestions  free items the customer has earned but not added yet
     */
    public function __construct(
        public readonly array $lines,
        public readonly string $couponStatus = self::COUPON_NONE,
        public readonly array $suggestions = [],
    ) {}

    public function total(): float
    {
        return round(array_sum(array_map(fn (CartLine $line): float => $line->discount(), $this->lines)), 2);
    }

    /**
     * Every applied rule, one entry per line it touched.
     *
     * @return list<array{line_key: int|string, rule_id: int, rule_name: string, amount: float, details: array<string, mixed>}>
     */
    public function applications(): array
    {
        $rows = [];

        foreach ($this->lines as $line) {
            foreach ($line->applied as $application) {
                $rows[] = ['line_key' => $line->key, ...$application];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total(),
            'coupon_status' => $this->couponStatus,
            'suggestions' => $this->suggestions,
            'lines' => array_map(fn (CartLine $line): array => [
                'key' => $line->key,
                'discount' => $line->discount(),
                'net' => round($line->remaining, 2),
                'applied' => $line->applied,
            ], $this->lines),
        ];
    }
}
