<?php

namespace App\Modules\Expenses\Enums;

use Carbon\CarbonImmutable;

enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /**
     * The date of the n-th run, counted from the start so a monthly expense on the 31st does not drift to the 28th.
     */
    public function dateOfRun(CarbonImmutable $startsOn, int $run): CarbonImmutable
    {
        return match ($this) {
            self::Weekly => $startsOn->addWeeks($run),
            self::Monthly => $startsOn->addMonthsNoOverflow($run),
            self::Yearly => $startsOn->addYearsNoOverflow($run),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
