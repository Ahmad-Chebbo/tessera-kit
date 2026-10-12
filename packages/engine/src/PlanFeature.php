<?php

namespace Tessera\Engine;

/**
 * A part of the product that a subscription plan can include or leave out, with the permissions it switches.
 * Modules may add permissions to the same feature key; the first label declared wins.
 */
final readonly class PlanFeature
{
    /**
     * @param  list<string>  $permissions
     * @param  int  $order  Position in plan screens, lowest first.
     */
    public function __construct(
        public string $key,
        public string $label,
        public array $permissions = [],
        public int $order = 100,
    ) {}
}
