<?php

namespace Tessera\Core\Pricing;

/**
 * One line of the cart as the engine sees it. Discounts shrink `remaining` as rules apply.
 */
class CartLine
{
    public float $remaining;

    public bool $locked = false;

    /**
     * @var list<array{rule_id: int, rule_name: string, amount: float, details: array<string, mixed>}>
     */
    public array $applied = [];

    public function __construct(
        public readonly int|string $key,
        public readonly int $productId,
        public readonly ?int $categoryId,
        public readonly float $quantity,
        public readonly float $baseQuantity,
        public readonly float $unitPrice,
    ) {
        $this->remaining = $this->total();
    }

    public function total(): float
    {
        return round($this->unitPrice * $this->quantity, 2);
    }

    public function discount(): float
    {
        return round($this->total() - $this->remaining, 2);
    }
}
