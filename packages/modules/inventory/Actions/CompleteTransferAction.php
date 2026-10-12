<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Enums\TransferStatus;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompleteTransferAction
{
    public function __construct(private StockService $stockService) {}

    /**
     * Complete a pending/approved transfer, moving stock between locations.
     */
    public function execute(Transfer $transfer, User $approver): Transfer
    {
        if (! in_array($transfer->status, [TransferStatus::Pending, TransferStatus::Approved])) {
            throw new InvalidArgumentException('Transfer must be pending or approved to complete.');
        }

        return DB::transaction(function () use ($transfer, $approver) {
            $transfer->load('items.product');

            foreach ($transfer->items as $item) {
                if (! $this->stockService->hasSufficientStock($item->product, $transfer->fromLocation, $item->quantity)) {
                    throw new InvalidArgumentException(
                        "Insufficient stock for {$item->product->name} at source location."
                    );
                }

                // Deduct from source
                StockMovement::create([
                    'company_id' => $transfer->company_id,
                    'product_id' => $item->product_id,
                    'location_id' => $transfer->from_location_id,
                    'type' => StockMovementType::TransferOut,
                    'quantity' => -abs($item->quantity),
                    'reference_type' => Transfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $approver->id,
                ]);

                // Add to destination
                StockMovement::create([
                    'company_id' => $transfer->company_id,
                    'product_id' => $item->product_id,
                    'location_id' => $transfer->to_location_id,
                    'type' => StockMovementType::TransferIn,
                    'quantity' => abs($item->quantity),
                    'reference_type' => Transfer::class,
                    'reference_id' => $transfer->id,
                    'created_by' => $approver->id,
                ]);
            }

            $transfer->update([
                'status' => TransferStatus::Completed,
                'approved_by' => $approver->id,
                'completed_at' => now(),
            ]);

            return $transfer->fresh();
        });
    }
}
