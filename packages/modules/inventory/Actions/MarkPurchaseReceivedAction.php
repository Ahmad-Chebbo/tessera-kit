<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MarkPurchaseReceivedAction
{
    /**
     * Receives a pending purchase and adds its items to the stock of its location. Two simultaneous requests cannot
     * both add the stock.
     *
     * @throws InvalidArgumentException when the purchase was already processed.
     */
    public function execute(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user): Purchase {
            $locked = Purchase::query()->whereKey($purchase->id)->lockForUpdate()->first();

            if ($locked->status !== 'pending') {
                throw new InvalidArgumentException('Purchase has already been processed.');
            }

            $locked->update(['status' => 'received']);

            foreach ($locked->items as $item) {
                StockMovement::create([
                    'tenant_id' => $locked->tenant_id,
                    'product_id' => $item->product_id,
                    'location_id' => $locked->location_id,
                    'type' => 'purchase',
                    'quantity' => $item->quantity,
                    'unit_id' => $item->unit_id,
                    'unit_quantity' => $item->unit_quantity,
                    'unit_to_base_factor' => $item->unit_to_base_factor,
                    'reference_type' => Purchase::class,
                    'reference_id' => $locked->id,
                    'reference' => 'PO-'.$locked->id,
                    'created_by' => $user->id,
                ]);
            }

            return $locked;
        });
    }
}
