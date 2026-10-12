<?php

namespace App\Modules\Subscription\Services;

use Tessera\Core\Contracts\PlanLimits;

/**
 * Answers the shared PlanLimits contract from the company's subscription. Someone who belongs to no company is never
 * limited.
 */
class EntitlementsPlanLimits implements PlanLimits
{
    public function __construct(private readonly Entitlements $entitlements) {}

    public function hasModule(?int $companyId, string $module): bool
    {
        return $companyId === null || $this->entitlements->hasModule($companyId, $module);
    }

    public function hasRoomFor(?int $companyId, string $key): bool
    {
        return $companyId === null || $this->entitlements->hasRoomFor($companyId, $key);
    }
}
