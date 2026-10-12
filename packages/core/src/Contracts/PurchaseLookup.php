<?php

namespace Tessera\Core\Contracts;

/**
 * Received purchases that can be recorded as expenses. The Inventory module answers; without it there are none.
 */
interface PurchaseLookup
{
    public function available(): bool;

    /**
     * @param  list<int>  $excluded  Purchases that are already linked elsewhere.
     * @return list<array{id: int, label: string, total: float, date: string, supplier: ?string}>
     */
    public function linkable(int $companyId, array $excluded = [], ?int $current = null): array;
}
