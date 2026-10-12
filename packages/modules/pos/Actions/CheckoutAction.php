<?php

namespace App\Modules\Pos\Actions;

use App\Modules\Orders\Actions\PlaceOrderAction;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Services\PosShiftService;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use InvalidArgumentException;
use Tessera\Core\Contracts\PlanLimits;

/**
 * A sale at the counter: the till's own rules (the cash drawer shift) around the order the Orders module places.
 */
class CheckoutAction
{
    public function __construct(
        private PlaceOrderAction $orders,
        private PosShiftService $posShifts,
    ) {}

    /**
     * Process a checkout: check the shift, then place the order. A sale that was already placed, because an offline
     * sale was sent twice, returns the existing order without asking for a shift again.
     *
     * @param  array<array{product_id: int, quantity: float, unit_id?: int, unit_price?: float, original_price?: float, original_currency?: string}>  $cartItems
     */
    public function execute(
        Branch $branch,
        array $cartItems,
        float $cashReceived,
        User $cashier,
        ?string $discountType = null,
        float $discountValue = 0,
        ?int $customerId = null,
        string $paymentType = 'cash',
        ?string $offlineId = null,
        bool $requireShift = true,
        ?string $couponCode = null,
    ): Order {
        if (empty($cartItems)) {
            throw new InvalidArgumentException('Cart cannot be empty.');
        }

        if ($offlineId !== null) {
            $existing = Order::query()->where('tenant_id', $branch->tenant_id)->where('offline_id', $offlineId)->first();

            if ($existing) {
                return $existing->load(['items.product', 'payment']);
            }
        }

        $posShift = $this->posShifts->current($cashier);

        if ($posShift === null && $requireShift && $branch->tenant->require_pos_shift && app(PlanLimits::class)->hasModule($branch->tenant_id, 'shifts')) {
            throw new InvalidArgumentException('Open a shift before selling.');
        }

        return $this->orders->execute(
            $branch,
            $cartItems,
            $cashReceived,
            $cashier,
            $discountType,
            $discountValue,
            $customerId,
            $paymentType,
            $offlineId,
            $couponCode,
            ['pos_shift_id' => $posShift?->id],
        );
    }
}
