<?php

namespace Tessera\Core\Http;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A date range with ready-made periods, shared by every page that filters by period.
 */
class PeriodRequest extends FormRequest
{
    public const MAX_RANGE_DAYS = 366;

    public const DEFAULT_RANGE_DAYS = 30;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Bad dates fall back to the default range instead of failing the page.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * The inclusive day range every query and chart on a report page must share.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dateRange(): array
    {
        $to = $this->parseDate('date_to') ?? $this->today();
        $from = $this->parseDate('date_from') ?? $to->subDays(self::DEFAULT_RANGE_DAYS - 1);

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->diffInDays($to) >= self::MAX_RANGE_DAYS) {
            $from = $to->subDays(self::MAX_RANGE_DAYS - 1);
        }

        return [$from, $to];
    }

    /**
     * Today in the tenant's timezone, so "today" means the shop's day rather than the server's.
     */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->user()?->tenant?->timezone ?: config('app.timezone'))->startOfDay();
    }

    /**
     * Ready-made periods for the report filters, as inclusive day ranges. The newest period ends today.
     *
     * @return list<array{key: string, from: string, to: string}>
     */
    public function presets(): array
    {
        $today = $this->today();
        $startOfWeek = $today->startOfWeek(CarbonImmutable::MONDAY);
        $lastMonth = $today->subMonthNoOverflow();

        $periods = [
            'today' => [$today, $today],
            'yesterday' => [$today->subDay(), $today->subDay()],
            'last_7_days' => [$today->subDays(6), $today],
            'last_30_days' => [$today->subDays(29), $today],
            'last_90_days' => [$today->subDays(89), $today],
            'this_week' => [$startOfWeek, $today],
            'last_week' => [$startOfWeek->subWeek(), $startOfWeek->subDay()],
            'this_month' => [$today->startOfMonth(), $today],
            'last_month' => [$lastMonth->startOfMonth(), $lastMonth->endOfMonth()->startOfDay()],
            'this_quarter' => [$today->startOfQuarter(), $today],
            'this_year' => [$today->startOfYear(), $today],
        ];

        return collect($periods)
            ->map(fn (array $range, string $key): array => ['key' => $key, 'from' => $range[0]->toDateString(), 'to' => $range[1]->toDateString()])
            ->values()
            ->all();
    }

    private function parseDate(string $key): ?CarbonImmutable
    {
        $value = $this->query($key);

        if (! is_string($value)) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (InvalidFormatException) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }
}
