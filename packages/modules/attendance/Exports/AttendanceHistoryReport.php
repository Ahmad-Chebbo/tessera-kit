<?php

namespace App\Modules\Attendance\Exports;

use App\Modules\Attendance\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class AttendanceHistoryReport extends ReportDefinition
{
    public function key(): string
    {
        return 'attendance-history';
    }

    public function permission(): string
    {
        return 'view attendance';
    }

    public function filterRules(int $tenantId): array
    {
        return [...$this->branchRules($tenantId), 'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('tenant_id', $tenantId)]];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('employee', Column::TEXT, 24),
            new Column('date', Column::DATE, 14),
            new Column('shift', Column::TEXT, 18),
            new Column('check_in', Column::DATETIME, 18),
            new Column('check_out', Column::DATETIME, 18),
            new Column('hours_worked', Column::NUMBER, 12),
            new Column('late_minutes', Column::INTEGER, 12),
            new Column('early_leave_minutes', Column::INTEGER, 14),
            new Column('source', Column::TEXT, 12),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        $query = $this->query($context)->with(['user:id,name', 'shiftTemplate:id,name']);

        foreach ($query->lazyByIdDesc((int) config('exports.chunk_size')) as $record) {
            yield [
                'employee' => $record->user?->name,
                'date' => $record->work_date,
                'shift' => $record->shiftTemplate?->name,
                'check_in' => $record->check_in_at,
                'check_out' => $record->check_out_at,
                'hours_worked' => $record->check_out_at ? round($record->worked_minutes / 60, 2) : null,
                'late_minutes' => $record->late_minutes,
                'early_leave_minutes' => $record->early_leave_minutes,
                'source' => $context->t('values.'.$record->source),
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return $this->query($context)->count();
    }

    public function summary(ExportContext $context): array
    {
        $query = $this->query($context);

        return [
            $this->figure($context, 'records', (clone $query)->count(), Column::INTEGER),
            $this->figure($context, 'total_hours', round(((int) (clone $query)->sum('worked_minutes')) / 60, 2), Column::NUMBER),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        $lines = array_filter([$this->periodLine($context), $this->branchLine($context)]);

        if ($userId = $context->filter('user_id')) {
            $lines[] = $context->t('filters.employee', ['value' => $context->tenant->users()->whereKey($userId)->value('name')]);
        }

        return array_values($lines);
    }

    /**
     * @return Builder<AttendanceRecord>
     */
    private function query(ExportContext $context): Builder
    {
        $query = AttendanceRecord::query()
            ->where('tenant_id', $context->tenant->id)
            ->when($this->branchId($context), fn ($inner, $branchId) => $inner->whereHas('user', fn ($user) => $user->where('branch_id', $branchId)));

        if ($userId = $context->filter('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($from = $context->date('date_from')) {
            $query->where('work_date', '>=', $from->toDateString());
        }

        if ($to = $context->date('date_to')) {
            $query->where('work_date', '<=', $to->toDateString());
        }

        return $query;
    }
}
