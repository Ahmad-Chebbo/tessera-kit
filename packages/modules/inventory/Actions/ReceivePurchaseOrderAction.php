<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseOrderAction
{
    /**
     * Receive a purchase order and add stock to inventory.
     */
    public function execute(Purchase $purchase): Purchase
    {
        if (! $purchase->isPending()) {
            throw new \Exception('Only pending purchases can be received.');
        }

        return DB::transaction(function () use ($purchase) {
            // Create stock movements for each item
            foreach ($purchase->items as $item) {
                StockMovement::create([
                    'company_id' => $purchase->company_id,
                    'product_id' => $item->product_id,
                    'location_id' => $purchase->location_id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => $item->quantity,
                    'notes' => "Purchase #{$purchase->id} - {$purchase->reference_number}",
                    'created_by' => $purchase->created_by,
                ]);
            }

            // Update purchase status
            $purchase->update([
                'status' => 'received',
                'received_at' => now(),
            ]);

            return $purchase->fresh();
        });
    }
}
