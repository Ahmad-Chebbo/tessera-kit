<?php

namespace App\Modules\Attendance\Http\Requests;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Unusable filters fall back to the default range instead of failing the page.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function from(int $defaultDaysBack = 30): CarbonImmutable
    {
        return $this->day('date_from') ?? CarbonImmutable::today()->subDays($defaultDaysBack);
    }

    public function to(): CarbonImmutable
    {
        $to = $this->day('date_to') ?? CarbonImmutable::today();

        return $to->lt($this->from()) ? $this->from() : $to;
    }

    private function day(string $key): ?CarbonImmutable
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
