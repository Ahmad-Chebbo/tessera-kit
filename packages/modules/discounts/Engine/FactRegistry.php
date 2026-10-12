<?php

namespace App\Modules\Discounts\Engine;

use InvalidArgumentException;
use Tessera\Core\Pricing\CartLine;

/**
 * Everything a rule condition can look at. The same list drives the rule builder in the UI, so adding a
 * fact here makes it available to tenants without touching anything else.
 */
class FactRegistry
{
    public const NUMBER = 'number';

    public const ID = 'id';

    public const TEXT = 'text';

    public const BOOL = 'bool';

    public const TIME = 'time';

    public const DATE = 'date';

    /**
     * @return array<string, array{scope: string, type: string, operators: list<string>, window: bool, options?: list<string>}>
     */
    public static function definitions(): array
    {
        $numeric = ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between'];
        $idOps = ['eq', 'neq', 'in', 'not_in'];

        return [
            'customer.is_selected' => ['scope' => 'customer', 'type' => self::BOOL, 'operators' => ['eq'], 'window' => false],
            'customer.id' => ['scope' => 'customer', 'type' => self::ID, 'operators' => $idOps, 'window' => false],
            'customer.group_id' => ['scope' => 'customer', 'type' => self::ID, 'operators' => $idOps, 'window' => false],
            'customer.orders_count' => ['scope' => 'customer', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => true],
            'customer.total_spent' => ['scope' => 'customer', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => true],
            'item.product_id' => ['scope' => 'item', 'type' => self::ID, 'operators' => $idOps, 'window' => false],
            'item.category_id' => ['scope' => 'item', 'type' => self::ID, 'operators' => $idOps, 'window' => false],
            'item.quantity' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'item.unit_price' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'item.line_total' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'item.bought_quantity' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => true],
            'item.bought_orders' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => true],
            'item.category_bought_quantity' => ['scope' => 'item', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => true],
            'cart.subtotal' => ['scope' => 'cart', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'cart.quantity' => ['scope' => 'cart', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'cart.lines' => ['scope' => 'cart', 'type' => self::NUMBER, 'operators' => $numeric, 'window' => false],
            'context.branch_id' => ['scope' => 'context', 'type' => self::ID, 'operators' => $idOps, 'window' => false],
            'context.payment_type' => ['scope' => 'context', 'type' => self::TEXT, 'operators' => ['eq', 'neq', 'in', 'not_in'], 'window' => false, 'options' => ['cash', 'debt']],
            'context.weekday' => ['scope' => 'context', 'type' => self::NUMBER, 'operators' => ['eq', 'neq', 'in', 'not_in'], 'window' => false, 'options' => ['0', '1', '2', '3', '4', '5', '6']],
            'context.time' => ['scope' => 'context', 'type' => self::TIME, 'operators' => ['gte', 'lte', 'between'], 'window' => false],
            'context.date' => ['scope' => 'context', 'type' => self::DATE, 'operators' => ['gte', 'lte', 'between', 'eq'], 'window' => false],
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function resolve(string $field, EvaluationContext $context, ?CartLine $line, array $params = []): mixed
    {
        $days = isset($params['window_days']) && (int) $params['window_days'] > 0 ? (int) $params['window_days'] : null;
        $history = $context->history;

        return match ($field) {
            'customer.is_selected' => $context->customerId !== null,
            'customer.id' => $context->customerId,
            'customer.group_id' => $context->customerGroupId,
            'customer.orders_count' => $history->ordersCount($days),
            'customer.total_spent' => $history->totalSpent($days),
            'item.product_id' => $line?->productId,
            'item.category_id' => $line?->categoryId,
            'item.quantity' => $line?->quantity,
            'item.unit_price' => $line?->unitPrice,
            'item.line_total' => $line?->total(),
            'item.bought_quantity' => $line ? $history->productQuantity($line->productId, $days) : 0.0,
            'item.bought_orders' => $line ? $history->productOrders($line->productId, $days) : 0.0,
            'item.category_bought_quantity' => $line ? $history->categoryQuantity($line->categoryId, $days) : 0.0,
            'cart.subtotal' => $context->subtotal(),
            'cart.quantity' => $context->quantity(),
            'cart.lines' => (float) count($context->lines),
            'context.branch_id' => $context->branchId,
            'context.payment_type' => $context->paymentType,
            'context.weekday' => $context->now->dayOfWeek,
            'context.time' => $context->now->format('H:i'),
            'context.date' => $context->now->format('Y-m-d'),
            default => throw new InvalidArgumentException("Unknown discount fact [{$field}]."),
        };
    }

    public static function isKnown(string $field): bool
    {
        return array_key_exists($field, self::definitions());
    }
}
