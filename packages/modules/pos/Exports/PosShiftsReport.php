<?php

namespace App\Modules\Pos\Exports;

use App\Modules\Pos\Models\PosShift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class PosShiftsReport extends ReportDefinition
{
    public function key(): string
    {
        return 'pos-shifts';
    }

    public function permission(): string
    {
        return 'view shift reports';
    }

    public function filterRules(int $tenantId): array
    {
        return [
            ...$this->branchRules($tenantId),
            'status' => ['nullable', Rule::in([PosShift::OPEN, PosShift::CLOSED])],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
        ];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('cashier', Column::TEXT, 20),
            new Column('branch', Column::TEXT, 16),
            new Column('opened_at', Column::DATETIME, 18),
            new Column('closed_at', Column::DATETIME, 18),
            new Column('status', Column::TEXT, 10),
            new Column('opening_float', Column::MONEY, 14),
            new Column('cash_sales', Column::MONEY, 14),
            new Column('credit_sales', Column::MONEY, 14),
            new Column('expected_cash', Column::MONEY, 14),
            new Column('counted_cash', Column::MONEY, 14),
            new Column('variance', Column::MONEY, 12),
            new Column('orders', Column::INTEGER, 10),
            new Column('voided_orders', Column::INTEGER, 10),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $query = $this->query($context)->with(['user:id,name', 'branch:id,name']);

        foreach ($query->lazyByIdDesc((int) config('exports.chunk_size')) as $shift) {
            $summary = $shift->summary ?? [];

            yield [
                'cashier' => $shift->user?->name,
                'branch' => $shift->branch?->name,
                'opened_at' => $shift->opened_at,
                'closed_at' => $shift->closed_at,
                'status' => $context->t('values.'.$shift->status),
                'opening_float' => (float) $shift->opening_float,
                'cash_sales' => $summary['cash_sales'] ?? null,
                'credit_sales' => $summary['credit_sales'] ?? null,
                'expected_cash' => $shift->expected_cash !== null ? (float) $shift->expected_cash : null,
                'counted_cash' => $shift->counted_cash !== null ? (float) $shift->counted_cash : null,
                'variance' => $shift->variance !== null ? (float) $shift->variance : null,
                'orders' => $summary['orders'] ?? null,
                'voided_orders' => $summary['voided_orders'] ?? null,
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return $this->query($context)->count();
    }

    public function summary(ExportContext $context): array
    {
        $closed = $this->query($context)->where('status', PosShift::CLOSED);

        return [
            $this->figure($context, 'shifts', $this->query($context)->count(), Column::INTEGER),
            $this->figure($context, 'variance', (float) (clone $closed)->sum('variance')),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        $lines = array_filter([$this->periodLine($context), $this->branchLine($context)]);

        if ($status = $context->filter('status')) {
            $lines[] = $context->t('filters.status', ['value' => $context->t("values.{$status}")]);
        }

        if ($userId = $context->filter('user_id')) {
            $lines[] = $context->t('filters.employee', ['value' => $context->tenant->users()->whereKey($userId)->value('name')]);
        }

        return array_values($lines);
    }

    /**
     * @return Builder<PosShift>
     */
    private function query(ExportContext $context): Builder
    {
        $query = PosShift::query()
            ->where('tenant_id', $context->tenant->id)
            ->when($this->branchId($context), fn ($inner, $branchId) => $inner->where('branch_id', $branchId));

        foreach (['status', 'user_id'] as $column) {
            if ($value = $context->filter($column)) {
                $query->where($column, $value);
            }
        }

        if ($from = $context->date('date_from')) {
            $query->whereDate('opened_at', '>=', $from->toDateString());
        }

        if ($to = $context->date('date_to')) {
            $query->whereDate('opened_at', '<=', $to->toDateString());
        }

        return $query;
    }
}
