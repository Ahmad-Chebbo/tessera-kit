<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Company\Models\Location;
use App\Modules\Inventory\Enums\TransferStatus;
use App\Modules\Inventory\Models\Transfer;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTransferAction
{
    /**
     * Create a new transfer between locations.
     *
     * @param  array<array{product_id: int, quantity: int}>  $items
     */
    public function execute(Location $from, Location $to, array $items, User $user, ?string $notes = null): Transfer
    {
        return DB::transaction(function () use ($from, $to, $items, $user, $notes) {
            $transfer = Transfer::create([
                'company_id' => $from->company_id,
                'from_location_id' => $from->id,
                'to_location_id' => $to->id,
                'status' => TransferStatus::Pending,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);

            foreach ($items as $item) {
                $transfer->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => abs($item['quantity']),
                ]);
            }

            return $transfer->load('items');
        });
    }
}
