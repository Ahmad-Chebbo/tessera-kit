<?php

namespace App\Modules\Discounts\Engine;

use App\Modules\Catalog\Models\Product;
use App\Modules\Discounts\Models\DiscountApplication;
use App\Modules\Discounts\Models\DiscountRule;
use App\Modules\Orders\Enums\OrderStatus;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Core\Pricing\CartLine;
use Tessera\Core\Pricing\DiscountResult;

/**
 * Decides which discount rules apply to a cart. Rules run in priority order (lowest number first); a rule that
 * is not stackable stops lower-priority rules from touching the lines it discounted. Percentages are taken from
 * what a line still costs after earlier rules.
 */
class DiscountEngine
{
    public function __construct(
        private readonly ConditionEvaluator $conditions,
        private readonly ActionCalculator $actions,
        private readonly RuleExpression $expressions,
        private readonly PlanLimits $limits,
    ) {}

    public function evaluate(EvaluationContext $context): DiscountResult
    {
        $couponStatus = $context->couponCode !== null && $context->couponCode !== '' ? DiscountResult::COUPON_UNKNOWN : DiscountResult::COUPON_NONE;

        if (! $this->limits->hasModule($context->tenantId, 'discounts')) {
            return new DiscountResult($context->lines, $couponStatus);
        }

        $rules = DiscountRule::query()
            ->where('tenant_id', $context->tenantId)
            ->runningAt($context->now)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();

        $suggestions = [];

        foreach ($rules as $rule) {
            $isCouponRule = $rule->coupon_code !== null && $rule->coupon_code !== '';

            if ($isCouponRule) {
                if (strcasecmp($rule->coupon_code, (string) $context->couponCode) !== 0) {
                    continue;
                }

                if ($this->usageExceeded($rule, $context)) {
                    $couponStatus = DiscountResult::COUPON_EXHAUSTED;

                    continue;
                }
            }

            $matched = array_values(array_filter(
                $context->lines,
                fn (CartLine $line): bool => ! $line->locked && $this->lineMatches($rule, $context, $line),
            ));

            if ($matched === []) {
                if ($isCouponRule && $couponStatus === DiscountResult::COUPON_UNKNOWN) {
                    $couponStatus = DiscountResult::COUPON_NOT_APPLICABLE;
                }

                continue;
            }

            ['amounts' => $amounts, 'pending' => $pending] = $this->actions->evaluate($rule, $matched, $context->lines);

            if ($pending > 0) {
                $suggestions[] = ['rule' => $rule, 'quantity' => $pending];
            }

            foreach ($matched as $line) {
                $amount = $amounts[$line->key] ?? 0.0;

                if ($amount <= 0) {
                    continue;
                }

                $line->remaining = round($line->remaining - $amount, 2);
                $line->applied[] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'amount' => $amount,
                    'details' => array_filter(['action' => $rule->action_type->value, 'coupon' => $isCouponRule ? $rule->coupon_code : null]),
                ];

                if (! $rule->stackable) {
                    $line->locked = true;
                }
            }

            if ($isCouponRule && $amounts !== []) {
                $couponStatus = DiscountResult::COUPON_APPLIED;
            }
        }

        return new DiscountResult($context->lines, $couponStatus, $this->describeSuggestions($suggestions));
    }

    /**
     * @param  list<array{rule: DiscountRule, quantity: float}>  $pending
     * @return list<array{rule_id: int, rule_name: string, quantity: float, products: list<array{id: int, name: string}>}>
     */
    private function describeSuggestions(array $pending): array
    {
        if ($pending === []) {
            return [];
        }

        $ids = collect($pending)->flatMap(fn (array $entry): array => (array) $entry['rule']->config('get_product_ids', []))->unique()->all();
        $names = Product::query()->whereIn('id', $ids)->pluck('name', 'id');

        return array_map(fn (array $entry): array => [
            'rule_id' => $entry['rule']->id,
            'rule_name' => $entry['rule']->name,
            'quantity' => $entry['quantity'],
            'products' => collect((array) $entry['rule']->config('get_product_ids', []))
                ->filter(fn ($id): bool => $names->has((int) $id))
                ->map(fn ($id): array => ['id' => (int) $id, 'name' => $names[(int) $id]])
                ->values()
                ->all(),
        ], $pending);
    }

    private function lineMatches(DiscountRule $rule, EvaluationContext $context, CartLine $line): bool
    {
        if (! $this->conditions->matches($rule->conditions, $context, $line)) {
            return false;
        }

        $expression = trim((string) $rule->expression);

        return $expression === '' || $this->expressions->matches($expression, $context, $line);
    }

    private function usageExceeded(DiscountRule $rule, EvaluationContext $context): bool
    {
        if ($rule->usage_limit === null && $rule->per_customer_limit === null) {
            return false;
        }

        $uses = fn () => DiscountApplication::query()
            ->join('orders', 'orders.id', '=', 'discount_applications.order_id')
            ->where('discount_applications.discount_rule_id', $rule->id)
            ->where('orders.status', '!=', OrderStatus::Voided->value);

        if ($rule->usage_limit !== null && $uses()->distinct()->count('discount_applications.order_id') >= $rule->usage_limit) {
            return true;
        }

        if ($rule->per_customer_limit !== null) {
            if ($context->customerId === null) {
                return true;
            }

            return $uses()->where('orders.customer_id', $context->customerId)->distinct()->count('discount_applications.order_id') >= $rule->per_customer_limit;
        }

        return false;
    }
}
