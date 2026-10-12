<?php

namespace Tessera\Core\Contracts;

/**
 * What a company's plan allows. The Subscription module answers; without it nothing is limited.
 */
interface PlanLimits
{
    public function hasModule(?int $companyId, string $module): bool;

    public function hasRoomFor(?int $companyId, string $key): bool;
}
