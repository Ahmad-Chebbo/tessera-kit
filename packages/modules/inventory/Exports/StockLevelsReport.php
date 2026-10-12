<?php

namespace App\Modules\Inventory\Exports;

use App\Modules\Catalog\Models\Product;
use App\Modules\Tenant\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class StockLevelsReport extends ReportDefinition
{
    public function key(): string
    {
        return 'stock-levels';
    }

    public function permission(): string
    {
        return 'view inventory';
    }

    public function filterRules(int $tenantId): array
    {
        return [
            ...$this->branchRules($tenantId),
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('tenant_id', $tenantId)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'search' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('product', Column::TEXT, 34),
            new Column('sku', Column::TEXT, 16),
            new Column('category', Column::TEXT, 22),
            new Column('stock', Column::NUMBER, 14),
            new Column('cost_price', Column::MONEY, 16),
            new Column('stock_value', Column::MONEY, 18),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        foreach ($this->query($context)->lazyById((int) config('exports.chunk_size'), 'products.id', 'id') as $product) {
            $stock = (float) $product->stock;

            yield [
                'product' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category_name,
                'stock' => $stock,
                'cost_price' => (float) $product->cost_price,
                'stock_value' => round($stock * (float) $product->cost_price, 2),
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return $this->query($context)->toBase()->getCountForPagination();
    }

    public function summary(ExportContext $context): array
    {
        $totals = DB::query()
            ->fromSub($this->query($context)->toBase(), 'levels')
            ->selectRaw('COUNT(*) as products, COALESCE(SUM(stock * cost_price), 0) as value')
            ->first();

        return [
            $this->figure($context, 'products', (int) $totals->products, Column::INTEGER),
            $this->figure($context, 'stock_value_total', (float) $totals->value),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        $location = $context->filter('location_id')
            ? Location::query()->where('tenant_id', $context->tenant->id)->whereKey($context->filter('location_id'))->value('name')
            : $context->t('values.all_locations');

        $lines = array_values(array_filter([$this->branchLine($context), $context->t('filters.location', ['value' => $location])]));

        if ($search = $context->filter('search')) {
            $lines[] = $context->t('filters.search', ['value' => $search]);
        }

        return $lines;
    }

    public function filename(ExportContext $context): string
    {
        return 'stock-levels_'.now()->toDateString();
    }

    /**
     * Stock is the sum of movements, never a stored number, so it is summed per product in the same query.
     *
     * @return Builder<Product>
     */
    private function query(ExportContext $context): Builder
    {
        $locationId = $context->filter('location_id');
        $bindings = [$context->tenant->id];
        $locationSql = '';

        if (($branchLocations = $this->branchLocationIds($context)) !== null) {
            $placeholders = implode(',', array_fill(0, count($branchLocations), '?')) ?: 'NULL';
            $locationSql .= " AND stock_movements.location_id IN ({$placeholders})";
            array_push($bindings, ...$branchLocations);
        }

        if ($locationId) {
            $locationSql .= ' AND stock_movements.location_id = ?';
            $bindings[] = (int) $locationId;
        }

        $query = Product::query()
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->where('products.tenant_id', $context->tenant->id)
            ->where('products.is_active', true)
            ->select('products.id', 'products.name', 'products.sku', 'products.cost_price', 'categories.name as category_name')
            ->selectRaw("COALESCE((SELECT SUM(stock_movements.quantity) FROM stock_movements WHERE stock_movements.product_id = products.id AND stock_movements.tenant_id = ?{$locationSql}), 0) as stock", $bindings);

        if ($categoryId = $context->filter('category_id')) {
            $query->where('products.category_id', $categoryId);
        }

        if ($search = $context->filter('search')) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $search).'%';
            $query->where(fn ($inner) => $inner->where('products.name', 'like', $like)->orWhere('products.sku', 'like', $like)->orWhere('products.barcode', 'like', $like));
        }

        return $query;
    }
}
