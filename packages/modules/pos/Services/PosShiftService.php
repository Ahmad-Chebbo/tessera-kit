<?php

namespace App\Modules\Pos\Services;

use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Team\Enums\ActivityEvent;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\ActivityLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PosShiftService
{
    public function __construct(private ActivityLogger $activity) {}

    public function current(User $user): ?PosShift
    {
        return PosShift::query()->where('open_user_id', $user->id)->first();
    }

    public function open(User $user, float $openingFloat): PosShift
    {
        if (! $user->branch_id) {
            throw new InvalidArgumentException('You are not assigned to a branch.');
        }

        try {
            $shift = PosShift::create([
                'tenant_id' => $user->tenant_id,
                'branch_id' => $user->branch_id,
                'user_id' => $user->id,
                'open_user_id' => $user->id,
                'status' => PosShift::OPEN,
                'opened_at' => now(),
                'opening_float' => $openingFloat,
            ]);
        } catch (QueryException) {
            throw new InvalidArgumentException('You already have an open shift.');
        }

        $this->activity->record(ActivityEvent::PosShiftOpened, 'Opened a POS shift', $shift, ['opening_float' => $openingFloat], $user);

        return $shift;
    }

    public function close(PosShift $shift, float $countedCash, ?string $notes, User $closedBy): PosShift
    {
        return DB::transaction(function () use ($shift, $countedCash, $notes, $closedBy): PosShift {
            $locked = PosShift::query()->lockForUpdate()->findOrFail($shift->id);

            if (! $locked->isOpen()) {
                throw new InvalidArgumentException('This shift is already closed.');
            }

            $summary = $this->summary($locked);
            $expected = round((float) $locked->opening_float + $summary['cash_sales'], 2);

            $locked->update([
                'status' => PosShift::CLOSED,
                'open_user_id' => null,
                'closed_at' => now(),
                'expected_cash' => $expected,
                'counted_cash' => $countedCash,
                'variance' => round($countedCash - $expected, 2),
                'notes' => $notes,
                'summary' => $summary,
            ]);

            $this->activity->record(
                ActivityEvent::PosShiftClosed,
                'Closed a POS shift',
                $locked,
                ['expected_cash' => $expected, 'counted_cash' => $countedCash, 'variance' => $locked->variance],
                $closedBy,
            );

            return $locked;
        });
    }

    /**
     * Totals for a shift. Cash sales are the cash that should be in the drawer; credit sales are not.
     *
     * @return array{orders: int, items: int, gross_sales: float, discounts: float, cash_sales: float, credit_sales: float, voided_orders: int, voided_total: float}
     */
    public function summary(PosShift $shift): array
    {
        $orders = Order::query()->where('pos_shift_id', $shift->id)->withSum('items', 'quantity')->get();
        $sold = $orders->where('status', '!=', OrderStatus::Voided);
        $voided = $orders->where('status', OrderStatus::Voided);

        return [
            'orders' => $sold->count(),
            'items' => (int) round((float) $sold->sum('items_sum_quantity')),
            'gross_sales' => round((float) $sold->sum('total'), 2),
            'discounts' => round((float) $sold->sum('discount_amount'), 2),
            'cash_sales' => round((float) $sold->where('payment_type', 'cash')->sum('total'), 2),
            'credit_sales' => round((float) $sold->where('payment_type', 'debt')->sum('total'), 2),
            'voided_orders' => $voided->count(),
            'voided_total' => round((float) $voided->sum('total'), 2),
        ];
    }
}
