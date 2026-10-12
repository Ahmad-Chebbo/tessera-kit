<?php

namespace App\Modules\Discounts\Templates;

use App\Modules\Discounts\Enums\DiscountAction;
use Illuminate\Validation\Rule;

/**
 * Ready-made rules for the promotions most shops run. Each template asks for a few values and builds a normal
 * discount rule, which the company can then edit like any other.
 */
class DiscountTemplates
{
    /**
     * @return array<string, array{params: list<array{name: string, type: string, default: mixed, required: bool}>, icon: string}>
     */
    public static function definitions(): array
    {
        $param = fn (string $name, string $type, mixed $default = null, bool $required = true): array => compact('name', 'type', 'default', 'required');

        return [
            'happy_hour' => ['icon' => 'clock', 'params' => [$param('percent', 'percent', 20), $param('from', 'time', '16:00'), $param('to', 'time', '18:00')]],
            'weekend' => ['icon' => 'calendar', 'params' => [$param('percent', 'percent', 10)]],
            'bulk' => ['icon' => 'layers', 'params' => [$param('quantity', 'quantity', 10), $param('percent', 'percent', 10)]],
            'buy_x_get_y' => ['icon' => 'gift', 'params' => [$param('buy', 'quantity', 10), $param('get', 'quantity', 1)]],
            'buy_get_item' => ['icon' => 'gift', 'params' => [$param('buy_products', 'products'), $param('buy', 'quantity', 1), $param('get_products', 'products'), $param('get', 'quantity', 1), $param('percent', 'percent', 100, false)]],
            'spend_percent' => ['icon' => 'receipt', 'params' => [$param('threshold', 'amount', 100), $param('percent', 'percent', 5)]],
            'spend_amount' => ['icon' => 'receipt', 'params' => [$param('threshold', 'amount', 100), $param('amount', 'amount', 10)]],
            'loyalty' => ['icon' => 'heart', 'params' => [$param('orders', 'quantity', 10), $param('percent', 'percent', 5), $param('days', 'days', null, false)]],
            'repeat_buyer' => ['icon' => 'repeat', 'params' => [$param('purchases', 'quantity', 10), $param('percent', 'percent', 10)]],
            'welcome' => ['icon' => 'sparkles', 'params' => [$param('percent', 'percent', 10)]],
            'category' => ['icon' => 'tag', 'params' => [$param('category', 'category'), $param('percent', 'percent', 15)]],
            'special_price' => ['icon' => 'badge', 'params' => [$param('product', 'product'), $param('price', 'amount')]],
            'group' => ['icon' => 'users', 'params' => [$param('group', 'group'), $param('percent', 'percent', 10)]],
            'cash' => ['icon' => 'banknote', 'params' => [$param('percent', 'percent', 3)]],
            'coupon' => ['icon' => 'ticket', 'params' => [$param('code', 'code'), $param('percent', 'percent', 10)]],
        ];
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    /**
     * Validation rules for the values a template asks for.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(string $key, int $companyId): array
    {
        $rules = [];

        foreach (self::definitions()[$key]['params'] as $param) {
            $required = $param['required'] ? 'required' : 'nullable';

            if ($param['type'] === 'products') {
                $rules[$param['name']] = [$required, 'array', 'min:1', 'max:50'];
                $rules[$param['name'].'.*'] = ['integer', Rule::exists('products', 'id')->where('company_id', $companyId)];

                continue;
            }

            $rules[$param['name']] = match ($param['type']) {
                'percent' => [$required, 'numeric', 'gt:0', 'max:100'],
                'amount' => [$required, 'numeric', 'gt:0', 'max:99999999'],
                'quantity' => [$required, 'integer', 'min:1', 'max:100000'],
                'days' => [$required, 'integer', 'min:1', 'max:3650'],
                'time' => [$required, 'date_format:H:i'],
                'code' => [$required, 'string', 'max:50', 'alpha_dash', Rule::unique('discount_rules', 'coupon_code')->where('company_id', $companyId)],
                'category' => [$required, 'integer', Rule::exists('categories', 'id')->where('company_id', $companyId)],
                'group' => [$required, 'integer', Rule::exists('customer_groups', 'id')->where('company_id', $companyId)],
                'product' => [$required, 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            };
        }

        return $rules;
    }

    /**
     * The rule a template produces for the given (already validated) values.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function build(string $key, array $values): array
    {
        $condition = fn (string $field, string $operator, mixed $value, array $params = []): array => ['type' => 'condition', 'field' => $field, 'operator' => $operator, 'value' => $value, 'params' => $params];
        $all = fn (array ...$children): array => ['type' => 'group', 'logic' => 'all', 'children' => $children];
        $percent = fn (float $value): array => ['action_type' => DiscountAction::Percent, 'action_value' => $value, 'action_config' => null];
        $number = fn (string $name): float => (float) $values[$name];

        $rule = match ($key) {
            'happy_hour' => [
                'name' => "Happy hour {$values['from']}–{$values['to']}",
                'description' => 'Discount during a daily time window.',
                'conditions' => $all($condition('context.time', 'between', [$values['from'], $values['to']])),
                ...$percent($number('percent')),
            ],
            'weekend' => [
                'name' => 'Weekend special',
                'description' => 'Discount on Saturdays and Sundays.',
                'conditions' => $all($condition('context.weekday', 'in', [0, 6])),
                ...$percent($number('percent')),
            ],
            'bulk' => [
                'name' => "Bulk discount ({$values['quantity']}+)",
                'description' => 'Discount on a product when the customer buys enough of it.',
                'conditions' => $all($condition('item.quantity', 'gte', (int) $values['quantity'])),
                ...$percent($number('percent')),
            ],
            'buy_x_get_y' => [
                'name' => "Buy {$values['buy']} get {$values['get']} free",
                'description' => 'Free units for every full set bought.',
                'conditions' => $all(),
                'action_type' => DiscountAction::BuyXGetY,
                'action_value' => 0,
                'action_config' => ['buy_quantity' => (int) $values['buy'], 'get_quantity' => (int) $values['get'], 'get_discount_percent' => 100],
            ],
            'buy_get_item' => [
                'name' => "Buy {$values['buy']}, get {$values['get']} free item".((int) $values['get'] === 1 ? '' : 's'),
                'description' => 'Buy some items and get other items free.',
                'conditions' => $all(),
                'action_type' => DiscountAction::BuyGetItem,
                'action_value' => 0,
                'action_config' => [
                    'buy_product_ids' => array_map('intval', $values['buy_products']),
                    'get_product_ids' => array_map('intval', $values['get_products']),
                    'buy_quantity' => (int) $values['buy'],
                    'get_quantity' => (int) $values['get'],
                    'get_discount_percent' => (float) ($values['percent'] ?? 100),
                ],
            ],
            'spend_percent' => [
                'name' => "Spend {$values['threshold']}+, get {$values['percent']}% off",
                'description' => 'Discount once the cart reaches a spend threshold.',
                'conditions' => $all($condition('cart.subtotal', 'gte', $number('threshold'))),
                ...$percent($number('percent')),
            ],
            'spend_amount' => [
                'name' => "Spend {$values['threshold']}+, get {$values['amount']} off",
                'description' => 'A fixed amount off once the cart reaches a spend threshold.',
                'conditions' => $all($condition('cart.subtotal', 'gte', $number('threshold'))),
                'action_type' => DiscountAction::Fixed,
                'action_value' => $number('amount'),
                'action_config' => ['apply_to' => 'total'],
            ],
            'loyalty' => [
                'name' => "Loyalty: {$values['orders']}+ orders",
                'description' => 'Reward customers who have ordered often.',
                'conditions' => $all($condition('customer.orders_count', 'gte', (int) $values['orders'], ! empty($values['days']) ? ['window_days' => (int) $values['days']] : [])),
                ...$percent($number('percent')),
            ],
            'repeat_buyer' => [
                'name' => "Repeat buyer ({$values['purchases']}+ bought)",
                'description' => 'Discount on a product the customer has already bought many times.',
                'conditions' => $all($condition('item.bought_quantity', 'gte', (int) $values['purchases'])),
                ...$percent($number('percent')),
            ],
            'welcome' => [
                'name' => 'Welcome: first order',
                'description' => 'Discount on a registered customer\'s first order.',
                'conditions' => $all($condition('customer.is_selected', 'eq', true), $condition('customer.orders_count', 'lte', 0)),
                ...$percent($number('percent')),
            ],
            'category' => [
                'name' => 'Category promotion',
                'description' => 'Discount on every product of a category.',
                'conditions' => $all($condition('item.category_id', 'eq', (int) $values['category'])),
                ...$percent($number('percent')),
            ],
            'special_price' => [
                'name' => 'Special price',
                'description' => 'A fixed selling price for one product.',
                'conditions' => $all($condition('item.product_id', 'eq', (int) $values['product'])),
                'action_type' => DiscountAction::Price,
                'action_value' => $number('price'),
                'action_config' => null,
            ],
            'group' => [
                'name' => 'Customer group discount',
                'description' => 'Discount for the members of a customer group.',
                'conditions' => $all($condition('customer.group_id', 'eq', (int) $values['group'])),
                ...$percent($number('percent')),
            ],
            'cash' => [
                'name' => 'Cash payment discount',
                'description' => 'Discount when the customer pays in cash.',
                'conditions' => $all($condition('context.payment_type', 'eq', 'cash')),
                ...$percent($number('percent')),
            ],
            'coupon' => [
                'name' => "Coupon {$values['code']}",
                'description' => 'Discount for customers who present a coupon code.',
                'conditions' => $all(),
                'coupon_code' => strtoupper((string) $values['code']),
                ...$percent($number('percent')),
            ],
        };

        return [
            'priority' => 100,
            'stackable' => true,
            'coupon_code' => null,
            'expression' => null,
            ...$rule,
        ];
    }
}
