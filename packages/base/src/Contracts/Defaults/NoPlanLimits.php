<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\PlanLimits;

class NoPlanLimits implements PlanLimits
{
    public function hasModule(?int $tenantId, string $module): bool
    {
        return true;
    }

    public function hasRoomFor(?int $tenantId, string $key): bool
    {
        return true;
    }
}
