<?php

namespace Tessera\Engine\Exports;

/**
 * One column of an exported report. The type decides how Excel formats the cell and how the PDF prints it.
 */
final readonly class Column
{
    public const TEXT = 'text';

    public const INTEGER = 'integer';

    public const NUMBER = 'number';

    public const MONEY = 'money';

    public const PERCENT = 'percent';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public function __construct(
        public string $key,
        public string $type = self::TEXT,
        public float $width = 16,
    ) {}

    public function isNumeric(): bool
    {
        return in_array($this->type, [self::INTEGER, self::NUMBER, self::MONEY, self::PERCENT], true);
    }
}
