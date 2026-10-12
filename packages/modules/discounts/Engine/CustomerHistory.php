<?php

namespace App\Modules\Discounts\Engine;

use App\Modules\Sales\Enums\OrderStatus;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * What a customer bought before. Voided orders never count. Results are remembered for the evaluation.
 */
class CustomerHistory
{
    /**
     * @var array<string, float>
     */
    private array $memo = [];

    public function __construct(
        private readonly int $companyId,
        private readonly ?int $customerId,
        private readonly CarbonImmutable $now,
    ) {}

    public function ordersCount(?int $days = null): float
    {
        return $this->remember("orders:{$days}", fn (): float => (float) $this->orders($days)->count());
    }

    public function totalSpent(?int $days = null): float
    {
        return $this->remember("spent:{$days}", fn (): float => (float) $this->orders($days)->sum('total'));
    }

    public function productQuantity(int $productId, ?int $days = null): float
    {
        return $this->remember("pq:{$productId}:{$days}", fn (): float => (float) $this->items($days)->where('order_items.product_id', $productId)->sum('order_items.quantity'));
    }

    public function productOrders(int $productId, ?int $days = null): float
    {
        return $this->remember("po:{$productId}:{$days}", fn (): float => (float) $this->items($days)->where('order_items.product_id', $productId)->distinct()->count('order_items.order_id'));
    }

    public function categoryQuantity(?int $categoryId, ?int $days = null): float
    {
        if ($categoryId === null) {
            return 0.0;
        }

        return $this->remember("cq:{$categoryId}:{$days}", fn (): float => (float) $this->items($days)
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.category_id', $categoryId)
            ->sum('order_items.quantity'));
    }

    /**
     * @return Builder<Order>
     */
    private function orders(?int $days): Builder
    {
        if ($this->customerId === null) {
            return Order::query()->whereRaw('1 = 0');
        }

        return Order::query()
            ->where('company_id', $this->companyId)
            ->where('customer_id', $this->customerId)
            ->where('status', '!=', OrderStatus::Voided->value)
            ->when($days, fn (Builder $query) => $query->where('created_at', '>=', $this->now->subDays($days)));
    }

    /**
     * @return Builder<OrderItem>
     */
    private function items(?int $days): Builder
    {
        if ($this->customerId === null) {
            return OrderItem::query()->whereRaw('1 = 0');
        }

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.company_id', $this->companyId)
            ->where('orders.customer_id', $this->customerId)
            ->where('orders.status', '!=', OrderStatus::Voided->value)
            ->when($days, fn (Builder $query) => $query->where('orders.created_at', '>=', $this->now->subDays($days)));
    }

    /**
     * @param  callable(): float  $resolve
     */
    private function remember(string $key, callable $resolve): float
    {
        return $this->memo[$key] ??= $resolve();
    }
}
