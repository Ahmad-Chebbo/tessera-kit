<?php

namespace Tessera\Core\Contracts;

/**
 * What a tenant's plan allows. The Subscription module answers; without it nothing is limited.
 */
interface PlanLimits
{
    public function hasModule(?int $tenantId, string $module): bool;

    public function hasRoomFor(?int $tenantId, string $key): bool;
}
