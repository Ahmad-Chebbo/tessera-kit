<?php

namespace App\Modules\Discounts\Engine;

use Tessera\Core\Pricing\CartLine;

/**
 * Evaluates a rule's condition tree for one cart line. A tree is nested groups ("all" or "any") of
 * comparisons such as {field: customer.orders_count, operator: gte, value: 10, params: {window_days: 90}}.
 */
class ConditionEvaluator
{
    private const MAX_DEPTH = 8;

    /**
     * @param  array<string, mixed>|null  $node
     */
    public function matches(?array $node, EvaluationContext $context, CartLine $line, int $depth = 0): bool
    {
        if ($node === null || $node === [] || $depth > self::MAX_DEPTH) {
            return $depth <= self::MAX_DEPTH;
        }

        if (($node['type'] ?? 'condition') === 'group') {
            $children = $node['children'] ?? [];

            if ($children === []) {
                return true;
            }

            $any = ($node['logic'] ?? 'all') === 'any';

            foreach ($children as $child) {
                $result = $this->matches($child, $context, $line, $depth + 1);

                if ($any && $result) {
                    return true;
                }

                if (! $any && ! $result) {
                    return false;
                }
            }

            return ! $any;
        }

        return $this->compare($node, $context, $line);
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function compare(array $condition, EvaluationContext $context, CartLine $line): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $definition = FactRegistry::definitions()[$field] ?? null;

        if ($definition === null) {
            return false;
        }

        $actual = FactRegistry::resolve($field, $context, $line, $condition['params'] ?? []);

        return $this->test((string) ($condition['operator'] ?? ''), $actual, $condition['value'] ?? null, $definition['type']);
    }

    private function test(string $operator, mixed $actual, mixed $expected, string $type): bool
    {
        if ($actual === null) {
            return false;
        }

        $normalize = fn (mixed $value): mixed => match ($type) {
            FactRegistry::NUMBER => is_numeric($value) ? (float) $value : null,
            FactRegistry::ID => is_numeric($value) ? (int) $value : null,
            FactRegistry::BOOL => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => is_scalar($value) ? strtolower((string) $value) : null,
        };

        $actual = $normalize($actual);

        if (in_array($operator, ['in', 'not_in'], true)) {
            $list = array_map($normalize, (array) $expected);
            $found = in_array($actual, $list, true) || ($type === FactRegistry::NUMBER && in_array($actual, array_map('floatval', $list), true));

            return $operator === 'in' ? $found : ! $found;
        }

        if ($operator === 'between') {
            $bounds = array_values((array) $expected);

            return count($bounds) === 2 && $actual >= $normalize($bounds[0]) && $actual <= $normalize($bounds[1]);
        }

        $expected = $normalize($expected);

        if ($expected === null) {
            return false;
        }

        return match ($operator) {
            'eq' => $actual === $expected || $actual == $expected,
            'neq' => $actual != $expected,
            'gt' => $actual > $expected,
            'gte' => $actual >= $expected,
            'lt' => $actual < $expected,
            'lte' => $actual <= $expected,
            default => false,
        };
    }
}
