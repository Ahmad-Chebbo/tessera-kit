<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\StockMovement;
use Tessera\Core\Contracts\LocationUsage;

class StockLocationUsage implements LocationUsage
{
    public function inUse(int $locationId): bool
    {
        return StockMovement::query()->where('location_id', $locationId)->exists();
    }
}
