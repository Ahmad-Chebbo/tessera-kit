<?php

namespace App\Modules\Leave\Services;

use App\Modules\Leave\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Counts the days a leave request really takes: the days between two dates that are neither weekend nor a public holiday.
 */
class LeaveDays
{
    /**
     * @return list<string> The working days from the first date to the last, inclusive, as YYYY-MM-DD.
     */
    public function between(int $tenantId, CarbonInterface $from, CarbonInterface $to): array
    {
        $holidays = Holiday::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn (mixed $date): string => CarbonImmutable::parse($date)->toDateString())
            ->all();

        $weekend = $this->weekendNumbers();
        $days = [];

        for ($day = CarbonImmutable::parse($from->toDateString()); $day->lte($to); $day = $day->addDay()) {
            if (! in_array($day->dayOfWeek, $weekend, true) && ! in_array($day->toDateString(), $holidays, true)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    /**
     * @return list<int> Carbon day numbers (0 is Sunday) of the configured weekend.
     */
    public function weekendNumbers(): array
    {
        $names = [
            CarbonInterface::SUNDAY => 'sunday',
            CarbonInterface::MONDAY => 'monday',
            CarbonInterface::TUESDAY => 'tuesday',
            CarbonInterface::WEDNESDAY => 'wednesday',
            CarbonInterface::THURSDAY => 'thursday',
            CarbonInterface::FRIDAY => 'friday',
            CarbonInterface::SATURDAY => 'saturday',
        ];

        return array_values(array_keys(array_intersect($names, (array) config('leave.weekend', ['saturday', 'sunday']))));
    }
}
