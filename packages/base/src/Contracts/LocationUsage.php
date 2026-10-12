<?php

namespace Tessera\Core\Contracts;

/**
 * Whether anything depends on a location. Inventory answers from its stock movements; without it a location is
 * never in use.
 */
interface LocationUsage
{
    public function inUse(int $locationId): bool;
}
