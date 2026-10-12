<?php

namespace App\Modules\Subscription\Services;

use Tessera\Core\Contracts\PlanLimits;

/**
 * Answers the shared PlanLimits contract from the tenant's subscription. Someone who belongs to no tenant is never
 * limited.
 */
class EntitlementsPlanLimits implements PlanLimits
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function hasModule(?int $tenantId, string $module): bool
    {
        return $tenantId === null || $this->entitlements->hasModule($tenantId, $module);
    }

    public function hasRoomFor(?int $tenantId, string $key): bool
    {
        return $tenantId === null || $this->entitlements->hasRoomFor($tenantId, $key);
    }
}
