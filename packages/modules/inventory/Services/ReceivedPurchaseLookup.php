<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Purchase;
use Illuminate\Database\Eloquent\Builder;
use Tessera\Core\Contracts\PurchaseLookup;

class ReceivedPurchaseLookup implements PurchaseLookup
{
    public function available(): bool
    {
        return true;
    }

    public function linkable(int $tenantId, array $excluded = [], ?int $current = null): array
    {
        return Purchase::query()
            ->with('supplier:id,name')
            ->where('tenant_id', $tenantId)
            ->where('status', 'received')
            ->where(fn (Builder $query) => $query->whereNotIn('id', $excluded)->when($current, fn (Builder $inner) => $inner->orWhere('id', $current)))
            ->latest('received_at')
            ->limit(100)
            ->get()
            ->map(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'label' => trim(($purchase->reference_number ?: '#'.$purchase->id).' · '.($purchase->supplier?->name ?? '')),
                'total' => (float) $purchase->total,
                'date' => ($purchase->received_at ?? $purchase->created_at)->toDateString(),
                'supplier' => $purchase->supplier?->name,
            ])
            ->all();
    }
}
