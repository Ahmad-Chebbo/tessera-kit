<?php

namespace App\Modules\Discounts\Engine;

use Illuminate\Support\Facades\DB;

/**
 * Checks a condition tree sent from the rule builder: known facts, operators that suit the fact, sensible
 * values, and ids that belong to the company.
 */
class ConditionTreeValidator
{
    public const MAX_DEPTH = 6;

    public const MAX_NODES = 40;

    private const ID_TABLES = [
        'item.product_id' => 'products',
        'item.category_id' => 'categories',
        'customer.id' => 'customers',
        'customer.group_id' => 'customer_groups',
        'context.branch_id' => 'branches',
    ];

    private int $nodes = 0;

    /**
     * @param  array<string, mixed>|null  $tree
     * @return list<string>
     */
    public function errors(?array $tree, int $companyId): array
    {
        $this->nodes = 0;

        if ($tree === null || $tree === []) {
            return [];
        }

        if (($tree['type'] ?? null) !== 'group') {
            return ['The conditions must start with a group.'];
        }

        return $this->node($tree, $companyId, 0);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return list<string>
     */
    private function node(array $node, int $companyId, int $depth): array
    {
        if (++$this->nodes > self::MAX_NODES) {
            return ['There are too many conditions.'];
        }

        if ($depth > self::MAX_DEPTH) {
            return ['Condition groups are nested too deeply.'];
        }

        if (($node['type'] ?? null) === 'group') {
            if (! in_array($node['logic'] ?? null, ['all', 'any'], true)) {
                return ['A group must match "all" or "any" of its conditions.'];
            }

            $errors = [];

            foreach ($node['children'] ?? [] as $child) {
                if (! is_array($child)) {
                    return ['A condition is malformed.'];
                }

                array_push($errors, ...$this->node($child, $companyId, $depth + 1));

                if (count($errors) >= 5) {
                    break;
                }
            }

            return $errors;
        }

        return $this->condition($node, $companyId);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @return list<string>
     */
    private function condition(array $condition, int $companyId): array
    {
        $field = (string) ($condition['field'] ?? '');
        $definition = FactRegistry::definitions()[$field] ?? null;

        if ($definition === null) {
            return ["Unknown condition field [{$field}]."];
        }

        $operator = (string) ($condition['operator'] ?? '');

        if (! in_array($operator, $definition['operators'], true)) {
            return ["The operator [{$operator}] cannot be used with [{$field}]."];
        }

        $value = $condition['value'] ?? null;
        $type = $definition['type'];

        $params = $condition['params'] ?? [];
        $window = $params['window_days'] ?? null;

        if ($window !== null && $window !== '' && (! $definition['window'] || ! is_numeric($window) || (int) $window < 1 || (int) $window > 3650)) {
            return ["The time window of [{$field}] must be between 1 and 3650 days."];
        }

        $scalars = match ($operator) {
            'in', 'not_in' => is_array($value) && $value !== [] ? array_values($value) : null,
            'between' => is_array($value) && count($value) === 2 ? array_values($value) : null,
            default => is_scalar($value) && $value !== '' ? [$value] : null,
        };

        if ($scalars === null) {
            return ["The value for [{$field}] is missing or has the wrong shape."];
        }

        foreach ($scalars as $item) {
            if (! is_scalar($item) || ! $this->valueFits($type, $item)) {
                return ["The value for [{$field}] is not valid."];
            }
        }

        if (isset(self::ID_TABLES[$field]) && in_array($operator, ['eq', 'neq', 'in', 'not_in'], true)) {
            $ids = array_unique(array_map('intval', $scalars));
            $found = DB::table(self::ID_TABLES[$field])->where('company_id', $companyId)->whereIn('id', $ids)->count();

            if ($found !== count($ids)) {
                return ["One of the selected values for [{$field}] does not exist."];
            }
        }

        return [];
    }

    private function valueFits(string $type, mixed $value): bool
    {
        return match ($type) {
            FactRegistry::NUMBER, FactRegistry::ID => is_numeric($value),
            FactRegistry::BOOL => in_array($value, [true, false, 1, 0, '1', '0', 'true', 'false'], true),
            FactRegistry::TIME => is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1,
            FactRegistry::DATE => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            default => is_string($value) && mb_strlen($value) <= 50,
        };
    }
}
