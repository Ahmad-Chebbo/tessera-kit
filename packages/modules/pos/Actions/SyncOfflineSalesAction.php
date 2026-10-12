<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Orders\Services\SaleRules;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class SyncOfflineSalesAction
{
    public function __construct(private readonly CheckoutAction $checkout) {}

    /**
     * Rings up sales that were taken offline. Every sale is handled on its own: one that fails is reported without
     * stopping the others, and one already synced earlier is reported as a duplicate.
     *
     * @param  array<int|string, mixed>  $sales
     * @return array{results: list<array<string, mixed>>, synced: int, failed: int}
     */
    public function execute(User $cashier, Branch $branch, array $sales): array
    {
        $results = [];

        foreach ($sales as $index => $sale) {
            $sale = is_array($sale) ? $sale : [];
            $offlineId = $sale['offline_id'] ?? null;

            try {
                $validator = Validator::make($sale, SaleRules::for($cashier, $sale));

                if ($validator->fails()) {
                    throw new InvalidArgumentException($validator->errors()->first());
                }

                $order = $this->checkout->execute(
                    branch: $branch,
                    cartItems: $sale['items'],
                    cashReceived: (float) $sale['cash_received'],
                    cashier: $cashier,
                    discountType: $sale['discount_type'] ?? null,
                    discountValue: (float) ($sale['discount_value'] ?? 0),
                    customerId: isset($sale['customer_id']) ? (int) $sale['customer_id'] : null,
                    paymentType: $sale['payment_type'] ?? 'cash',
                    offlineId: $offlineId,
                    requireShift: false,
                    couponCode: $sale['coupon_code'] ?? null,
                );

                $results[] = [
                    'index' => $index,
                    'success' => true,
                    'duplicate' => ! $order->wasRecentlyCreated,
                    'offline_id' => $offlineId,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ];
            } catch (InvalidArgumentException $e) {
                $results[] = ['index' => $index, 'success' => false, 'offline_id' => $offlineId, 'error' => $e->getMessage()];
            } catch (Throwable $e) {
                report($e);

                $results[] = ['index' => $index, 'success' => false, 'offline_id' => $offlineId, 'error' => 'The sale could not be processed.'];
            }
        }

        return [
            'results' => $results,
            'synced' => collect($results)->where('success', true)->count(),
            'failed' => collect($results)->where('success', false)->count(),
        ];
    }
}
