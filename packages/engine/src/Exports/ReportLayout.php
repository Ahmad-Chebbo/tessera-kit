<?php

namespace Tessera\Engine\Exports;

/**
 * A report ready to be written: heading lines, headline figures, column headings and a lazy row source. Both the
 * Excel and the PDF writer consume this, so the two always agree.
 */
final readonly class ReportLayout
{
    /**
     * @param  list<string>  $filterLines
     * @param  list<array{label: string, value: mixed, type: string}>  $summary
     * @param  list<Column>  $columns
     * @param  list<string>  $headings
     * @param  iterable<array<string, mixed>>  $rows
     */
    public function __construct(
        public ExportContext $context,
        public string $title,
        public string $companyName,
        public string $generatedLine,
        public array $filterLines,
        public array $summary,
        public array $columns,
        public array $headings,
        public iterable $rows,
    ) {}
}
