<?php

namespace App\Modules\Team\Http\Requests;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;

class ActivityLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view activity log') ?? false;
    }

    /**
     * Unusable filters are ignored instead of failing the page.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function from(): ?CarbonImmutable
    {
        return $this->dateFilter('date_from')?->startOfDay();
    }

    public function to(): ?CarbonImmutable
    {
        return $this->dateFilter('date_to')?->endOfDay();
    }

    private function dateFilter(string $key): ?CarbonImmutable
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
