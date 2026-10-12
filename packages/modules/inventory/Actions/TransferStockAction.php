<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\UnitConversionService;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Location;
use Illuminate\Support\Facades\DB;

class TransferStockAction
{
    public function __construct(private readonly UnitConversionService $units) {}

    /**
     * Moves stock between two locations as a pair of movements per product, all under one reference.
     *
     * @param  list<array{product_id: int, quantity: float|int|string, unit_id?: int|null}>  $items
     * @return string The reference shared by the movements.
     */
    public function execute(User $user, Location $from, Location $to, array $items, ?string $notes = null): string
    {
        $reference = 'TRF-'.now()->format('YmdHis');

        DB::transaction(function () use ($user, $from, $to, $items, $notes, $reference): void {
            foreach ($items as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->tenant_id !== $user->tenant_id) {
                    abort(403);
                }

                $unitId = $item['unit_id'] ?? null;
                $userQuantity = (float) $item['quantity'];
                $baseQuantity = $userQuantity;
                $unitQuantity = null;
                $unitFactor = null;

                if ($unitId) {
                    $conversion = $this->units->convertToBase($product->id, $unitId, $userQuantity);
                    $baseQuantity = $conversion['base_quantity'];
                    $unitQuantity = $userQuantity;
                    $unitFactor = $conversion['factor'];
                }

                foreach ([[$from, 'transfer_out', -$baseQuantity], [$to, 'transfer_in', $baseQuantity]] as [$location, $type, $quantity]) {
                    StockMovement::create([
                        'tenant_id' => $user->tenant_id,
                        'product_id' => $product->id,
                        'location_id' => $location->id,
                        'type' => $type,
                        'quantity' => $quantity,
                        'unit_id' => $unitId,
                        'unit_quantity' => $unitQuantity,
                        'unit_to_base_factor' => $unitFactor,
                        'reference' => $reference,
                        'notes' => $notes,
                        'created_by' => $user->id,
                    ]);
                }
            }
        });

        return $reference;
    }
}
