<?php

namespace App\Modules\Discounts\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Discounts\Engine\DiscountEngine;
use App\Modules\Discounts\Engine\EvaluationContext;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Tessera\Core\Pricing\CartLine;

/**
 * Runs the engine on a cart without saving anything: for the POS to show discounts live and for managers
 * to test rules.
 */
class DiscountPreview
{
    public function __construct(private readonly DiscountEngine $engine) {}

    /**
     * @param  list<array{product_id: int, quantity: float|int|string}>  $items
     * @return array<string, mixed>
     */
    public function run(User $user, array $items, ?int $customerId = null, ?string $couponCode = null, string $paymentType = 'cash'): array
    {
        $company = $user->company;
        $currency = $company->currency ?? 'USD';
        $customer = $customerId ? Customer::query()->where('company_id', $user->company_id)->find($customerId) : null;

        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $product = Product::query()->where('company_id', $user->company_id)->find($item['product_id']);

            if ($product === null) {
                continue;
            }

            $quantity = abs((float) $item['quantity']);
            $price = round($product->getSellingPriceIn($currency) ?? $product->getSellingPrice(), 2);
            $lines[] = new CartLine($index, $product->id, $product->category_id, $quantity, $quantity, $price);
        }

        $result = $this->engine->evaluate(new EvaluationContext(
            companyId: $user->company_id,
            branchId: (int) $user->branch_id,
            customerId: $customer?->id,
            customerGroupId: $customer?->customer_group_id,
            paymentType: $paymentType,
            now: CarbonImmutable::now($company->timezone ?: config('app.timezone')),
            lines: $lines,
            couponCode: $couponCode,
        ));

        $payload = $result->toArray();
        $payload['subtotal'] = array_sum(array_map(fn (CartLine $line): float => $line->total(), $lines));

        foreach ($payload['lines'] as &$line) {
            $line['product_id'] = $lines[$line['key']]->productId;
        }

        return $payload;
    }
}
