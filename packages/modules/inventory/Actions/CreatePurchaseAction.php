<?php

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Services\UnitConversionService;
use App\Modules\Company\Models\ExchangeRate;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\PurchaseItem;
use App\Modules\Team\Models\User;
use Illuminate\Support\Facades\DB;

class CreatePurchaseAction
{
    public function __construct(private readonly UnitConversionService $units) {}

    /**
     * Records a purchase order in the pending state; stock changes when it is received.
     *
     * @param  array{supplier_id: int, location_id: int, currency: string, reference_number?: ?string, notes?: ?string, items: list<array{product_id: int, quantity: float|int|string, unit_cost: float|int|string, unit_id?: int|null}>}  $data
     */
    public function execute(User $user, array $data): Purchase
    {
        $companyCurrency = $user->company->currency ?? 'USD';
        $exchangeRate = ExchangeRate::getRate($user->company_id, $data['currency'], $companyCurrency);

        return DB::transaction(function () use ($user, $data, $exchangeRate): Purchase {
            $totalAmount = collect($data['items'])->sum(fn ($item) => $item['quantity'] * $item['unit_cost']);

            $purchase = Purchase::create([
                'company_id' => $user->company_id,
                'supplier_id' => $data['supplier_id'],
                'location_id' => $data['location_id'],
                'currency' => $data['currency'],
                'exchange_rate' => $exchangeRate,
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $totalAmount,
                'total' => $totalAmount,
                'status' => 'pending',
                'created_by' => $user->id,
            ]);

            foreach ($data['items'] as $item) {
                $unitId = $item['unit_id'] ?? null;
                $userQuantity = (float) $item['quantity'];
                $baseQuantity = $userQuantity;
                $unitQuantity = null;
                $unitFactor = null;

                if ($unitId) {
                    $conversion = $this->units->convertToBase($item['product_id'], $unitId, $userQuantity);
                    $baseQuantity = $conversion['base_quantity'];
                    $unitQuantity = $userQuantity;
                    $unitFactor = $conversion['factor'];
                }

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $baseQuantity,
                    'unit_id' => $unitId,
                    'unit_quantity' => $unitQuantity,
                    'unit_to_base_factor' => $unitFactor,
                    'unit_cost' => $item['unit_cost'],
                    'total' => $userQuantity * $item['unit_cost'],
                ]);
            }

            return $purchase;
        });
    }
}
