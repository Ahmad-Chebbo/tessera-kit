<?php

namespace Tessera\Core\Contracts;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What a customer has bought. The Sales module answers; without it there is no activity to show.
 */
interface CustomerActivity
{
    public function available(): bool;

    /**
     * @return array{orders: int, spent: float, debt: float, paid_debt: float}
     */
    public function summary(int $customerId): array;

    /**
     * Order count and unpaid debt of several customers at once, keyed by customer id; customers without orders may be
     * left out.
     *
     * @param  list<int>  $customerIds
     * @return array<int, array{orders: int, debt: float}>
     */
    public function totals(array $customerIds): array;

    /**
     * @return LengthAwarePaginator<int, mixed>
     */
    public function orders(int $customerId, int $perPage = 15): LengthAwarePaginator;
}
