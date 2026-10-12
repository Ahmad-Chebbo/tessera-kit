<?php

namespace Tessera\Engine\Exports;

use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Everything a report needs to produce its rows: who asked, for which tenant, in which language and with
 * which (already validated) filters.
 */
class ExportContext
{
    /**
     * @var array<string, mixed>
     */
    private array $memo = [];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public readonly User $user,
        public readonly Tenant $tenant,
        public readonly string $locale,
        public readonly array $filters = [],
    ) {}

    public function isRtl(): bool
    {
        return $this->locale === 'ar';
    }

    public function currency(): string
    {
        return $this->tenant->currency ?: 'USD';
    }

    public function timezone(): string
    {
        return $this->tenant->timezone ?: config('app.timezone');
    }

    public function filter(string $key, mixed $default = null): mixed
    {
        $value = $this->filters[$key] ?? null;

        return $value === null || $value === '' ? $default : $value;
    }

    public function date(string $key): ?CarbonImmutable
    {
        $value = $this->filter($key);

        return is_string($value) ? CarbonImmutable::createFromFormat('!Y-m-d', $value) : null;
    }

    /**
     * Shows a stored (UTC) moment in the tenant's own clock.
     */
    public function local(?CarbonInterface $moment): ?CarbonImmutable
    {
        return $moment === null ? null : CarbonImmutable::instance($moment)->setTimezone($this->timezone());
    }

    /**
     * @param  array<string, mixed>  $replace
     */
    public function t(string $key, array $replace = []): string
    {
        return (string) __("exports::exports.{$key}", $replace, $this->locale);
    }

    /**
     * Remembers an expensive value for the length of the export, such as a row count that is also needed later.
     *
     * @template T
     *
     * @param  callable(): T  $resolve
     * @return T
     */
    public function remember(string $key, callable $resolve): mixed
    {
        return $this->memo[$key] ??= $resolve();
    }
}
