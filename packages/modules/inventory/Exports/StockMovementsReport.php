<?php

namespace App\Modules\Inventory\Exports;

use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class StockMovementsReport extends ReportDefinition
{
    public function key(): string
    {
        return 'stock-movements';
    }

    public function permission(): string
    {
        return 'view inventory';
    }

    public function filterRules(int $companyId): array
    {
        return [
            ...$this->branchRules($companyId),
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('company_id', $companyId)],
            'type' => ['nullable', Rule::in(array_column(StockMovementType::cases(), 'value'))],
        ];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('datetime', Column::DATETIME, 18),
            new Column('product', Column::TEXT, 30),
            new Column('sku', Column::TEXT, 14),
            new Column('location', Column::TEXT, 20),
            new Column('movement_type', Column::TEXT, 14),
            new Column('quantity', Column::NUMBER, 12),
            new Column('created_by', Column::TEXT, 18),
            new Column('notes', Column::TEXT, 26),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $query = $this->query($context)->with(['product:id,name,sku', 'location:id,name', 'creator:id,name']);

        foreach ($query->lazyByIdDesc((int) config('exports.chunk_size')) as $movement) {
            yield [
                'datetime' => $movement->created_at,
                'product' => $movement->product?->name,
                'sku' => $movement->product?->sku,
                'location' => $movement->location?->name,
                'movement_type' => $context->t('values.'.$movement->type->value),
                'quantity' => (float) $movement->quantity,
                'created_by' => $movement->creator?->name,
                'notes' => $movement->notes,
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return $this->query($context)->count();
    }

    public function filterLines(ExportContext $context): array
    {
        $lines = array_filter([$this->periodLine($context), $this->branchLine($context)]);

        if ($type = $context->filter('type')) {
            $lines[] = $context->t('filters.type', ['value' => $context->t("values.{$type}")]);
        }

        return array_values($lines);
    }

    /**
     * @return Builder<StockMovement>
     */
    private function query(ExportContext $context): Builder
    {
        $query = StockMovement::query()
            ->where('company_id', $context->company->id)
            ->when($this->branchLocationIds($context) !== null, fn ($inner) => $inner->whereIn('location_id', $this->branchLocationIds($context)));

        foreach (['product_id', 'location_id', 'type'] as $column) {
            if ($value = $context->filter($column)) {
                $query->where($column, $value);
            }
        }

        if ($from = $context->date('date_from')) {
            $query->whereDate('created_at', '>=', $from->toDateString());
        }

        if ($to = $context->date('date_to')) {
            $query->whereDate('created_at', '<=', $to->toDateString());
        }

        return $query;
    }
}
