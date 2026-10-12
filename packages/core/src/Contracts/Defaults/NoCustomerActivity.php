<?php

namespace Tessera\Core\Contracts\Defaults;

use Illuminate\Pagination\LengthAwarePaginator;
use Tessera\Core\Contracts\CustomerActivity;

class NoCustomerActivity implements CustomerActivity
{
    public function available(): bool
    {
        return false;
    }

    public function summary(int $customerId): array
    {
        return ['orders' => 0, 'spent' => 0.0, 'debt' => 0.0, 'paid_debt' => 0.0];
    }

    public function orders(int $customerId, int $perPage = 15): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, $perPage);
    }
}
