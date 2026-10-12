<?php

namespace App\Modules\Discounts\Engine;

use Carbon\CarbonImmutable;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Tessera\Core\Pricing\CartLine;
use Throwable;

/**
 * Optional free-form condition on a rule, for example:
 *   customer.orders_count >= 10 and item.category_id in [3, 4] and customer_spent(90) > 500
 *
 * Only the facts and functions listed here exist; there is no way to reach PHP constants, classes or functions.
 */
class RuleExpression
{
    public const MAX_LENGTH = 500;

    private const VARIABLES = ['customer', 'item', 'cart', 'context', 'engine'];

    private ExpressionLanguage $language;

    public function __construct()
    {
        $this->language = new class extends ExpressionLanguage
        {
            protected function registerFunctions(): void {}
        };

        $this->language->register('min', fn (string ...$args) => 'min('.implode(', ', $args).')', fn (array $values, ...$args) => min(...$args));
        $this->language->register('max', fn (string ...$args) => 'max('.implode(', ', $args).')', fn (array $values, ...$args) => max(...$args));
        $this->language->register('customer_orders', fn () => '', fn (array $values, $days = null) => $values['engine']->context->history->ordersCount($this->days($days)));
        $this->language->register('customer_spent', fn () => '', fn (array $values, $days = null) => $values['engine']->context->history->totalSpent($this->days($days)));
        $this->language->register('item_bought', fn () => '', fn (array $values, $days = null) => $values['engine']->line ? $values['engine']->context->history->productQuantity($values['engine']->line->productId, $this->days($days)) : 0.0);
        $this->language->register('item_orders', fn () => '', fn (array $values, $days = null) => $values['engine']->line ? $values['engine']->context->history->productOrders($values['engine']->line->productId, $this->days($days)) : 0.0);
        $this->language->register('category_bought', fn () => '', fn (array $values, $days = null) => $values['engine']->line ? $values['engine']->context->history->categoryQuantity($values['engine']->line->categoryId, $this->days($days)) : 0.0);
    }

    /**
     * An expression that cannot be evaluated simply does not match, so a broken rule never blocks a sale.
     */
    public function matches(string $expression, EvaluationContext $context, CartLine $line): bool
    {
        try {
            return (bool) $this->language->evaluate($expression, $this->variables($context, $line));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns an error message, or null when the expression is usable.
     */
    public function validate(string $expression): ?string
    {
        if (mb_strlen($expression) > self::MAX_LENGTH) {
            return 'The expression is too long.';
        }

        try {
            $this->language->lint($expression, self::VARIABLES);

            $context = new EvaluationContext(0, 0, null, null, 'cash', CarbonImmutable::now(), [], null);
            $line = new CartLine(0, 0, null, 1.0, 1.0, 1.0);
            $this->language->evaluate($expression, $this->variables($context, $line));
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function variables(EvaluationContext $context, CartLine $line): array
    {
        $history = $context->history;

        return [
            'customer' => new LazyFacts([
                'is_selected' => fn () => $context->customerId !== null,
                'id' => fn () => $context->customerId ?? 0,
                'group_id' => fn () => $context->customerGroupId ?? 0,
                'orders_count' => fn () => $history->ordersCount(),
                'total_spent' => fn () => $history->totalSpent(),
            ]),
            'item' => new LazyFacts([
                'product_id' => fn () => $line->productId,
                'category_id' => fn () => $line->categoryId ?? 0,
                'quantity' => fn () => $line->quantity,
                'unit_price' => fn () => $line->unitPrice,
                'line_total' => fn () => $line->total(),
                'bought_quantity' => fn () => $history->productQuantity($line->productId),
                'bought_orders' => fn () => $history->productOrders($line->productId),
                'category_bought_quantity' => fn () => $history->categoryQuantity($line->categoryId),
            ]),
            'cart' => new LazyFacts([
                'subtotal' => fn () => $context->subtotal(),
                'quantity' => fn () => $context->quantity(),
                'lines' => fn () => count($context->lines),
            ]),
            'context' => new LazyFacts([
                'branch_id' => fn () => $context->branchId,
                'payment_type' => fn () => $context->paymentType,
                'weekday' => fn () => $context->now->dayOfWeek,
                'time' => fn () => $context->now->format('H:i'),
                'date' => fn () => $context->now->format('Y-m-d'),
            ]),
            'engine' => (object) ['context' => $context, 'line' => $line],
        ];
    }

    private function days(mixed $days): ?int
    {
        return is_numeric($days) && (int) $days > 0 ? (int) $days : null;
    }
}
