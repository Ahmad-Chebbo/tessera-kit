<?php

namespace Tessera\Core\Contracts\Defaults;

use Tessera\Core\Contracts\PurchaseLookup;

class NoPurchaseLookup implements PurchaseLookup
{
    public function available(): bool
    {
        return false;
    }

    public function linkable(int $tenantId, array $excluded = [], ?int $current = null): array
    {
        return [];
    }
}
