<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\LocationUsage;

class NoLocationUsage implements LocationUsage
{
    public function inUse(int $locationId): bool
    {
        return false;
    }
}
