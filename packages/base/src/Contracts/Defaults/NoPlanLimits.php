<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\PlanLimits;

class NoPlanLimits implements PlanLimits
{
    public function hasModule(?int $companyId, string $module): bool
    {
        return true;
    }

    public function hasRoomFor(?int $companyId, string $key): bool
    {
        return true;
    }
}
