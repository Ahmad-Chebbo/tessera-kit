<?php

namespace App\Modules\Pos\Exports;

use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Pos\Services\PosShiftService;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Validation\ValidationException;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class PosShiftClosingReport extends ReportDefinition
{
    public function __construct(private readonly PosShiftService $shifts) {}

    public function key(): string
    {
        return 'pos-shift-closing';
    }

    public function permission(): string
    {
        return 'open pos shift';
    }

    public function filterRules(int $tenantId): array
    {
        return ['shift_id' => ['required', 'integer']];
    }

    /**
     * A shift's own cashier may export its report; anyone else needs the shift reports permission.
     */
    public function authorize(ExportContext $context): void
    {
        $shift = $this->shift($context);

        if ($shift === null) {
            throw ValidationException::withMessages(['filters.shift_id' => $context->t('errors.shift_required')]);
        }

        if ($shift->user_id !== $context->user->id && (! $context->user->can('view shift reports') || ! app(BranchAccess::class)->allows($context->user, $shift->branch_id))) {
            throw ValidationException::withMessages(['report' => $context->t('errors.not_allowed')]);
        }
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('order_number', Column::TEXT, 24),
            new Column('datetime', Column::DATETIME, 20),
            new Column('payment_type', Column::TEXT, 14),
            new Column('status', Column::TEXT, 14),
            new Column('total', Column::MONEY, 16),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $shift = $this->shift($context);

        foreach (Order::query()->where('pos_shift_id', $shift->id)->lazyById((int) config('exports.chunk_size')) as $order) {
            yield [
                'order_number' => $order->order_number,
                'datetime' => $order->created_at,
                'payment_type' => $context->t('values.'.$order->payment_type),
                'status' => $context->t('values.'.$order->status->value),
                'total' => (float) $order->total,
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        $shift = $this->shift($context);

        return $shift ? Order::query()->where('pos_shift_id', $shift->id)->count() : 0;
    }

    public function summary(ExportContext $context): array
    {
        $shift = $this->shift($context);
        $summary = $shift->isOpen() ? $this->shifts->summary($shift) : ($shift->summary ?? []);
        $expected = $shift->expected_cash !== null ? (float) $shift->expected_cash : round((float) $shift->opening_float + ($summary['cash_sales'] ?? 0), 2);

        return [
            $this->figure($context, 'cashier', $shift->user?->name, Column::TEXT),
            $this->figure($context, 'branch', $shift->branch?->name, Column::TEXT),
            $this->figure($context, 'opened_at', $shift->opened_at, Column::DATETIME),
            $this->figure($context, 'closed_at', $shift->closed_at, Column::DATETIME),
            $this->figure($context, 'orders', $summary['orders'] ?? 0, Column::INTEGER),
            $this->figure($context, 'items', $summary['items'] ?? 0, Column::INTEGER),
            $this->figure($context, 'gross_sales', $summary['gross_sales'] ?? 0),
            $this->figure($context, 'discounts', $summary['discounts'] ?? 0),
            $this->figure($context, 'cash_sales', $summary['cash_sales'] ?? 0),
            $this->figure($context, 'credit_sales', $summary['credit_sales'] ?? 0),
            $this->figure($context, 'voided_orders', $summary['voided_orders'] ?? 0, Column::INTEGER),
            $this->figure($context, 'voided_total', $summary['voided_total'] ?? 0),
            $this->figure($context, 'opening_float', (float) $shift->opening_float),
            $this->figure($context, 'expected_cash', $expected),
            $this->figure($context, 'counted_cash', $shift->counted_cash !== null ? (float) $shift->counted_cash : null),
            $this->figure($context, 'variance', $shift->variance !== null ? (float) $shift->variance : null),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        return [$context->t('filters.shift', ['value' => $context->filter('shift_id')])];
    }

    public function filename(ExportContext $context): string
    {
        return 'shift-closing_'.$context->filter('shift_id');
    }

    private function shift(ExportContext $context): ?PosShift
    {
        return $context->remember('shift', fn (): ?PosShift => PosShift::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->where('tenant_id', $context->tenant->id)
            ->find($context->filter('shift_id')));
    }
}
