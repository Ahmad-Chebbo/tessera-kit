<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\UnitConversionService;
use App\Modules\Company\Models\Location;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;

class CreateAdjustmentAction
{
    public function __construct(private UnitConversionService $unitConversionService) {}

    /**
     * Create a stock adjustment (positive or negative).
     * Used for corrections, damage, expiry, etc.
     */
    public function execute(
        Product $product,
        Location $location,
        float $quantity,
        User $user,
        ?string $notes = null,
        ?int $unitId = null,
    ): StockMovement {
        return DB::transaction(function () use ($product, $location, $quantity, $user, $notes, $unitId) {
            $baseQuantity = $quantity;
            $unitQuantity = null;
            $unitFactor = null;

            if ($unitId) {
                $conversion = $this->unitConversionService->convertToBase($product->id, $unitId, abs($quantity));
                $unitQuantity = abs($quantity);
                $unitFactor = $conversion['factor'];
                // Preserve the sign of the adjustment
                $baseQuantity = $quantity >= 0 ? $conversion['base_quantity'] : -$conversion['base_quantity'];
            }

            return StockMovement::create([
                'company_id' => $location->company_id,
                'product_id' => $product->id,
                'location_id' => $location->id,
                'type' => StockMovementType::Adjustment,
                'quantity' => $baseQuantity,
                'unit_id' => $unitId,
                'unit_quantity' => $unitQuantity,
                'unit_to_base_factor' => $unitFactor,
                'notes' => $notes,
                'created_by' => $user->id,
            ]);
        });
    }
}
