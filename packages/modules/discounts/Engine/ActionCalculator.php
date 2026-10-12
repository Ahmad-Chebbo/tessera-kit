<?php

namespace App\Modules\Discounts\Engine;

use App\Modules\Discounts\Enums\DiscountAction;
use App\Modules\Discounts\Models\DiscountRule;
use Tessera\Core\Pricing\CartLine;

/**
 * Works out how much a rule takes off each of the lines it matched. A line can never go below zero.
 */
class ActionCalculator
{
    /**
     * @param  list<CartLine>  $lines  the lines the rule may discount
     * @param  list<CartLine>  $cartLines  the whole cart, for rules that count other items
     * @return array<int|string, float> amount per line key
     */
    public function calculate(DiscountRule $rule, array $lines, array $cartLines = []): array
    {
        return $this->evaluate($rule, $lines, $cartLines)['amounts'];
    }

    /**
     * Like calculate(), and also says how many free units the customer earned but did not yet put in the cart.
     *
     * @param  list<CartLine>  $lines
     * @param  list<CartLine>  $cartLines
     * @return array{amounts: array<int|string, float>, pending: float}
     */
    public function evaluate(DiscountRule $rule, array $lines, array $cartLines = []): array
    {
        $pending = 0.0;

        $amounts = match ($rule->action_type) {
            DiscountAction::Percent => $this->percent($rule, $lines),
            DiscountAction::Fixed => $this->fixed($rule, $lines),
            DiscountAction::Price => $this->specialPrice($rule, $lines),
            DiscountAction::BuyXGetY => $this->buyXGetY($rule, $lines),
            DiscountAction::BuyGetItem => $this->buyGetItem($rule, $lines, $cartLines ?: $lines, $pending),
        };

        $cap = $rule->config('cap_amount');

        if (is_numeric($cap) && (float) $cap > 0) {
            $amounts = $this->capTotal($amounts, (float) $cap);
        }

        $result = [];

        foreach ($lines as $line) {
            $amount = round(min(max($amounts[$line->key] ?? 0.0, 0.0), $line->remaining), 2);

            if ($amount > 0) {
                $result[$line->key] = $amount;
            }
        }

        return ['amounts' => $result, 'pending' => $pending];
    }

    /**
     * Buy N of some items, get M of other items at a discount (free by default). Rewards are taken from what is already
     * in the cart, cheapest first, and never more than the customer earned.
     *
     * @param  list<CartLine>  $lines
     * @param  list<CartLine>  $cartLines
     * @return array<int|string, float>
     */
    private function buyGetItem(DiscountRule $rule, array $lines, array $cartLines, float &$pending): array
    {
        $buyIds = array_map('intval', (array) $rule->config('buy_product_ids', []));
        $getIds = array_map('intval', (array) $rule->config('get_product_ids', []));
        $buy = (float) $rule->config('buy_quantity', 0);
        $get = (float) $rule->config('get_quantity', 0);
        $percent = min(max((float) $rule->config('get_discount_percent', 100), 0.0), 100.0);

        if ($buyIds === [] || $getIds === [] || $buy <= 0 || $get <= 0) {
            return [];
        }

        $bought = array_sum(array_map(fn (CartLine $line): float => in_array($line->productId, $buyIds, true) ? $line->quantity : 0.0, $cartLines));
        $overlaps = array_intersect($buyIds, $getIds) !== [];
        $sets = floor($bought / ($overlaps ? $buy + $get : $buy));
        $maxSets = (float) $rule->config('max_sets', 0);

        if ($maxSets > 0) {
            $sets = min($sets, $maxSets);
        }

        $free = $sets * $get;

        if ($free <= 0) {
            return [];
        }

        $rewards = array_values(array_filter($lines, fn (CartLine $line): bool => in_array($line->productId, $getIds, true)));
        usort($rewards, fn (CartLine $a, CartLine $b): int => $a->unitPrice <=> $b->unitPrice);

        $amounts = [];

        foreach ($rewards as $line) {
            if ($free <= 0) {
                break;
            }

            $units = min($free, $line->quantity);
            $amounts[$line->key] = $units * $line->unitPrice * $percent / 100;
            $free -= $units;
        }

        $pending = max($free, 0.0);

        return $amounts;
    }

    /**
     * @param  list<CartLine>  $lines
     * @return array<int|string, float>
     */
    private function percent(DiscountRule $rule, array $lines): array
    {
        $percent = min(max($rule->action_value, 0.0), 100.0);

        return $this->perLine($lines, fn (CartLine $line): float => $line->remaining * $percent / 100);
    }

    /**
     * @param  list<CartLine>  $lines
     * @return array<int|string, float>
     */
    private function fixed(DiscountRule $rule, array $lines): array
    {
        $value = max($rule->action_value, 0.0);

        return match ($rule->config('apply_to', 'each_line')) {
            'each_unit' => $this->perLine($lines, fn (CartLine $line): float => $value * $line->quantity),
            'total' => $this->shareOut($value, $lines),
            default => $this->perLine($lines, fn (CartLine $line): float => $value),
        };
    }

    /**
     * @param  list<CartLine>  $lines
     * @return array<int|string, float>
     */
    private function specialPrice(DiscountRule $rule, array $lines): array
    {
        $price = max($rule->action_value, 0.0);

        return $this->perLine($lines, fn (CartLine $line): float => max($line->unitPrice - $price, 0.0) * $line->quantity);
    }

    /**
     * Buy N, get M free (or at a percentage off): every N + M units contain M discounted ones.
     *
     * @param  list<CartLine>  $lines
     * @return array<int|string, float>
     */
    private function buyXGetY(DiscountRule $rule, array $lines): array
    {
        $buy = max((float) $rule->config('buy_quantity', 0), 0.0);
        $get = max((float) $rule->config('get_quantity', 0), 0.0);
        $percent = min(max((float) $rule->config('get_discount_percent', 100), 0.0), 100.0);

        if ($buy + $get <= 0 || $get <= 0) {
            return [];
        }

        return $this->perLine($lines, function (CartLine $line) use ($buy, $get, $percent): float {
            $free = floor($line->quantity / ($buy + $get)) * $get;

            return $free * $line->unitPrice * $percent / 100;
        });
    }

    /**
     * @param  list<CartLine>  $lines
     * @param  callable(CartLine): float  $amount
     * @return array<int|string, float>
     */
    private function perLine(array $lines, callable $amount): array
    {
        $result = [];

        foreach ($lines as $line) {
            $result[$line->key] = $amount($line);
        }

        return $result;
    }

    /**
     * One amount split across lines in proportion to what each still costs.
     *
     * @param  list<CartLine>  $lines
     * @return array<int|string, float>
     */
    private function shareOut(float $value, array $lines): array
    {
        $base = array_sum(array_map(fn (CartLine $line): float => $line->remaining, $lines));

        if ($base <= 0) {
            return [];
        }

        $value = min($value, $base);
        $result = [];

        foreach ($lines as $line) {
            $result[$line->key] = $value * $line->remaining / $base;
        }

        return $result;
    }

    /**
     * @param  array<int|string, float>  $amounts
     * @return array<int|string, float>
     */
    private function capTotal(array $amounts, float $cap): array
    {
        $total = array_sum($amounts);

        if ($total <= $cap || $total <= 0) {
            return $amounts;
        }

        return array_map(fn (float $amount): float => $amount * $cap / $total, $amounts);
    }
}
