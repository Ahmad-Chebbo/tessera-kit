<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Company\Models\Branch;
use App\Modules\Sales\Models\HeldOrder;
use App\Modules\Team\Models\User;

class HoldOrderAction
{
    /**
     * Parks a cart so the cashier can serve someone else and come back to it.
     *
     * @param  array{name?: ?string, items: list<array<string, mixed>>, total: float|int|string}  $data
     */
    public function execute(User $cashier, Branch $branch, array $data): HeldOrder
    {
        return HeldOrder::create([
            'company_id' => $cashier->company_id,
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'name' => ($data['name'] ?? null) ?: 'Order #'.now()->format('His'),
            'items' => $data['items'],
            'total' => $data['total'],
        ]);
    }
}
