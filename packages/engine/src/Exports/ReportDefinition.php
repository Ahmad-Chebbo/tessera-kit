<?php

namespace Tessera\Engine\Exports;

use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Validation\Rule;

/**
 * An exportable report. It describes its columns and filters and hands out rows lazily, so even a very large
 * report is read in chunks and never held in memory as a whole.
 */
abstract class ReportDefinition
{
    abstract public function key(): string;

    /**
     * The permission that lets a user see this report's data. Exporting also needs the "export reports" permission.
     */
    abstract public function permission(): string;

    /**
     * Extra checks for reports that depend on what is being exported (for example a single shift).
     * Throw a validation or authorization exception to refuse.
     */
    public function authorize(ExportContext $context): void {}

    /**
     * @return list<Column>
     */
    abstract public function columns(ExportContext $context): array;

    /**
     * Rows keyed by column key. Values are raw: numbers as numbers, moments as Carbon instances.
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function rows(ExportContext $context): iterable;

    abstract public function count(ExportContext $context): int;

    /**
     * Rules for the filters the report accepts, keyed by filter name.
     *
     * @return array<string, array<int, mixed>>
     */
    public function filterRules(int $tenantId): array
    {
        return [];
    }

    /**
     * Headline figures printed above the table.
     *
     * @return list<array{label: string, value: mixed, type: string}>
     */
    public function summary(ExportContext $context): array
    {
        return [];
    }

    /**
     * Human readable lines describing the applied filters, such as the period.
     *
     * @return list<string>
     */
    public function filterLines(ExportContext $context): array
    {
        return [];
    }

    /**
     * A short ASCII name for the downloaded file.
     */
    public function filename(ExportContext $context): string
    {
        $from = $context->date('date_from');
        $to = $context->date('date_to');

        return str_replace('_', '-', $this->key()).($from && $to ? "_{$from->toDateString()}_{$to->toDateString()}" : '_'.now()->toDateString());
    }

    /**
     * @return array{label: string, value: mixed, type: string}
     */
    protected function figure(ExportContext $context, string $label, mixed $value, string $type = Column::MONEY): array
    {
        return ['label' => $context->t("columns.{$label}"), 'value' => $value, 'type' => $type];
    }

    /**
     * The branch an export is limited to: the one asked for by people who may see every branch, and always the user's
     * own branch for everyone else.
     */
    protected function branchId(ExportContext $context): ?int
    {
        return app(BranchAccess::class)->effective($context->user, $context->filter('branch_id'));
    }

    /**
     * Ids of the locations inside the branch an export is limited to, or null when it covers every branch.
     *
     * @return list<int>|null
     */
    protected function branchLocationIds(ExportContext $context): ?array
    {
        $id = $this->branchId($context);

        if ($id === null) {
            return null;
        }

        return Location::query()
            ->where('tenant_id', $context->tenant->id)
            ->where('branch_id', $id)
            ->pluck('id')
            ->all();
    }

    protected function branchLine(ExportContext $context): ?string
    {
        $id = $this->branchId($context);

        if ($id === null) {
            return null;
        }

        return $context->t('filters.branch', ['value' => Branch::query()->where('tenant_id', $context->tenant->id)->whereKey($id)->value('name')]);
    }

    /**
     * Validation of the shared branch filter.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function branchRules(int $tenantId): array
    {
        return ['branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)]];
    }

    /**
     * A period line such as "Period: 2026-09-15 – 2026-10-14".
     */
    protected function periodLine(ExportContext $context): ?string
    {
        $from = $context->date('date_from');
        $to = $context->date('date_to');

        if ($from === null && $to === null) {
            return null;
        }

        return $context->t('filters.period', ['from' => $from?->toDateString() ?? '…', 'to' => $to?->toDateString() ?? '…']);
    }
}
