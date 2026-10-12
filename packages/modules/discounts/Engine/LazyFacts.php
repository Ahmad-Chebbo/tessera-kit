<?php

namespace App\Modules\Discounts\Engine;

use Closure;
use InvalidArgumentException;

/**
 * A read-only bag of values for expressions. Values are computed on first use, so history queries only run
 * when a rule actually reads them.
 */
class LazyFacts
{
    /**
     * @var array<string, mixed>
     */
    private array $resolved = [];

    /**
     * @param  array<string, Closure(): mixed>  $resolvers
     */
    public function __construct(private readonly array $resolvers) {}

    public function __get(string $name): mixed
    {
        if (! isset($this->resolvers[$name])) {
            throw new InvalidArgumentException("Unknown fact [{$name}].");
        }

        return $this->resolved[$name] ??= ($this->resolvers[$name])();
    }

    public function __isset(string $name): bool
    {
        return isset($this->resolvers[$name]);
    }
}
