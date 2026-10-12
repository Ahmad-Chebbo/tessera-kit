<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Location;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;

class ReceivePurchaseAction
{
    /**
     * Receive purchased stock at a location.
     *
     * @param  array<array{product_id: int, quantity: int}>  $items
     * @return array<StockMovement>
     */
    public function execute(Location $location, array $items, User $user, ?string $notes = null): array
    {
        return DB::transaction(function () use ($location, $items, $user, $notes) {
            $movements = [];

            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                $movements[] = StockMovement::create([
                    'company_id' => $location->company_id,
                    'product_id' => $product->id,
                    'location_id' => $location->id,
                    'type' => StockMovementType::Purchase,
                    'quantity' => abs($item['quantity']),
                    'notes' => $notes,
                    'created_by' => $user->id,
                ]);
            }

            return $movements;
        });
    }
}
