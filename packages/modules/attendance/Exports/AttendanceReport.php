<?php

namespace App\Modules\Attendance\Exports;

use App\Modules\Attendance\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Tessera\Engine\Exports\Column;
use Tessera\Engine\Exports\ExportContext;
use Tessera\Engine\Exports\ReportDefinition;

class AttendanceReport extends ReportDefinition
{
    public function __construct(private readonly AttendanceService $attendance) {}

    public function key(): string
    {
        return 'attendance';
    }

    public function permission(): string
    {
        return 'view attendance';
    }

    public function filterRules(int $companyId): array
    {
        return [...$this->branchRules($companyId), 'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('company_id', $companyId)]];
    }

    public function columns(ExportContext $context): array
    {
        return [
            new Column('employee', Column::TEXT, 28),
            new Column('days_present', Column::INTEGER, 14),
            new Column('hours_worked', Column::NUMBER, 14),
            new Column('late_count', Column::INTEGER, 14),
            new Column('late_minutes', Column::INTEGER, 14),
            new Column('absences', Column::INTEGER, 12),
            new Column('excused', Column::INTEGER, 16),
        ];
    }

    public function rows(ExportContext $context): iterable
    {
        foreach ($this->report($context)['employees'] as $employee) {
            yield [
                'employee' => $employee['name'],
                'days_present' => $employee['days_present'],
                'hours_worked' => round($employee['worked_minutes'] / 60, 2),
                'late_count' => $employee['late_count'],
                'late_minutes' => $employee['late_minutes'],
                'absences' => $employee['absences'],
                'excused' => $employee['excused'],
            ];
        }
    }

    public function count(ExportContext $context): int
    {
        return count($this->report($context)['employees']);
    }

    public function summary(ExportContext $context): array
    {
        $totals = $this->report($context)['totals'];

        return [
            $this->figure($context, 'total_hours', round($totals['worked_minutes'] / 60, 2), Column::NUMBER),
            $this->figure($context, 'late_count', $totals['late_count'], Column::INTEGER),
            $this->figure($context, 'absences', $totals['absences'], Column::INTEGER),
            $this->figure($context, 'excused', $totals['excused'], Column::INTEGER),
        ];
    }

    public function filterLines(ExportContext $context): array
    {
        [$from, $to] = $this->range($context);
        $lines = array_values(array_filter([$context->t('filters.period', ['from' => $from->toDateString(), 'to' => $to->toDateString()]), $this->branchLine($context)]));

        if ($userId = $context->filter('user_id')) {
            $lines[] = $context->t('filters.employee', ['value' => $context->company->users()->whereKey($userId)->value('name')]);
        }

        return $lines;
    }

    public function filename(ExportContext $context): string
    {
        [$from, $to] = $this->range($context);

        return "attendance_{$from->toDateString()}_{$to->toDateString()}";
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function range(ExportContext $context): array
    {
        $today = CarbonImmutable::now($context->timezone())->startOfDay();
        $to = $context->date('date_to') ?? $today;
        $from = $context->date('date_from') ?? $to->subDays(29);

        return $from->greaterThan($to) ? [$to, $from] : [$from, $to];
    }

    /**
     * @return array{employees: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    private function report(ExportContext $context): array
    {
        return $context->remember('attendance', function () use ($context): array {
            [$from, $to] = $this->range($context);

            return $this->attendance->report($context->company->id, $from, $to, $context->filter('user_id') ? (int) $context->filter('user_id') : null, $this->branchId($context));
        });
    }
}
