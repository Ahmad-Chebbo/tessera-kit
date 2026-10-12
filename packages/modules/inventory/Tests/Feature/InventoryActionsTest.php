<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Inventory\Actions\CreatePurchaseAction;
use App\Modules\Inventory\Actions\MarkPurchaseReceivedAction;
use App\Modules\Inventory\Actions\TransferStockAction;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Team\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

function actionStockAt(Product $product, Location $location): float
{
    return (float) StockMovement::where('product_id', $product->id)->where('location_id', $location->id)->sum('quantity');
}

beforeEach(function () {
    $this->company = Company::factory()->create(['currency' => 'USD']);
    $this->branch = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->shop = Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Shop']);
    $this->store = Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id, 'name' => 'Store']);
    $this->user = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->branch->id]);
    $this->product = Product::factory()->create(['company_id' => $this->company->id, 'is_active' => true]);
});

describe('transferring stock', function () {
    it('takes stock out of one location and into the other under one reference', function () {
        StockMovement::factory()->create(['company_id' => $this->company->id, 'product_id' => $this->product->id, 'location_id' => $this->shop->id, 'quantity' => 20]);

        $reference = app(TransferStockAction::class)->execute($this->user, $this->shop, $this->store, [['product_id' => $this->product->id, 'quantity' => 5]], 'restock');

        expect($reference)->toStartWith('TRF-')
            ->and(actionStockAt($this->product, $this->shop))->toBe(15.0)
            ->and(actionStockAt($this->product, $this->store))->toBe(5.0)
            ->and(StockMovement::where('reference', $reference)->count())->toBe(2)
            ->and(StockMovement::where('reference', $reference)->pluck('type')->map->value->sort()->values()->all())->toBe(['transfer_in', 'transfer_out'])
            ->and(StockMovement::where('reference', $reference)->pluck('notes')->unique()->all())->toBe(['restock']);
    });

    it('refuses products of another company and writes nothing', function () {
        $stranger = Product::factory()->create(['company_id' => Company::factory()->create()->id]);

        expect(fn () => app(TransferStockAction::class)->execute($this->user, $this->shop, $this->store, [
            ['product_id' => $this->product->id, 'quantity' => 1],
            ['product_id' => $stranger->id, 'quantity' => 1],
        ]))->toThrow(HttpException::class);

        expect(StockMovement::count())->toBe(0);
    });
});

describe('purchases', function () {
    beforeEach(function () {
        $this->supplier = Supplier::create(['company_id' => $this->company->id, 'name' => 'Acme']);
        $this->data = [
            'supplier_id' => $this->supplier->id, 'location_id' => $this->shop->id, 'currency' => 'USD', 'reference_number' => 'PO-7',
            'items' => [['product_id' => $this->product->id, 'quantity' => 10, 'unit_cost' => 3]],
        ];
    });

    it('records a pending purchase with its items and total', function () {
        $purchase = app(CreatePurchaseAction::class)->execute($this->user, $this->data);

        expect($purchase->status)->toBe('pending')
            ->and((float) $purchase->total)->toBe(30.0)
            ->and((float) $purchase->exchange_rate)->toBe(1.0)
            ->and($purchase->items)->toHaveCount(1)
            ->and((float) $purchase->items->first()->quantity)->toBe(10.0)
            ->and(StockMovement::count())->toBe(0);
    });

    it('adds the items to stock when received, once', function () {
        $purchase = app(CreatePurchaseAction::class)->execute($this->user, $this->data);

        app(MarkPurchaseReceivedAction::class)->execute($purchase, $this->user);

        expect($purchase->fresh()->status)->toBe('received')
            ->and(actionStockAt($this->product, $this->shop))->toBe(10.0)
            ->and(StockMovement::where('reference', 'PO-'.$purchase->id)->count())->toBe(1)
            ->and(StockMovement::where('reference_type', $purchase::class)->where('reference_id', $purchase->id)->count())->toBe(1);

        expect(fn () => app(MarkPurchaseReceivedAction::class)->execute($purchase->fresh(), $this->user))
            ->toThrow(InvalidArgumentException::class, 'Purchase has already been processed.');
        expect(actionStockAt($this->product, $this->shop))->toBe(10.0);
    });
});
