<?php

namespace App\Modules\Inventory\Dashboard;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Location;
use Tessera\Engine\Dashboard\DashboardWidget;

class LowStockWidget implements DashboardWidget
{
    private const THRESHOLD = 10;

    public function key(): string
    {
        return 'low-stock';
    }

    public function component(): string
    {
        return 'LowStock';
    }

    public function order(): int
    {
        return 10;
    }

    public function permission(): ?string
    {
        return null;
    }

    /**
     * @return list<array{id: int, name: string, stock: int, image_url: string|null}>
     */
    public function data(User $user, ?int $branchId): array
    {
        $products = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->get(['id', 'name']);

        $stock = StockMovement::query()
            ->whereIn('product_id', $products->pluck('id'))
            ->when($branchId, fn ($query) => $query->whereIn(
                'location_id',
                Location::query()->where('branch_id', $branchId)->select('id'),
            ))
            ->groupBy('product_id')
            ->selectRaw('product_id, SUM(quantity) as stock')
            ->pluck('stock', 'product_id');

        return $products
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'stock' => (int) ($stock[$product->id] ?? 0),
                'image_url' => $product->image_url,
            ])
            ->filter(fn (array $row): bool => $row['stock'] <= self::THRESHOLD)
            ->take(10)
            ->values()
            ->all();
    }
}
