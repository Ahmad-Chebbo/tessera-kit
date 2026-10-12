<?php

namespace App\Modules\Pos\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Orders\Models\Order;
use App\Modules\Orders\Services\ReceiptService;
use App\Modules\Pos\Actions\CheckoutAction;
use App\Modules\Pos\Actions\HoldOrderAction;
use App\Modules\Pos\Actions\SyncOfflineSalesAction;
use App\Modules\Pos\Http\Requests\CheckoutRequest;
use App\Modules\Pos\Models\HeldOrder;
use App\Modules\Tenant\Models\ExchangeRate;
use App\Modules\Tenant\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Core\Contracts\CartPricing;

class POSController extends Controller
{
    private const MAX_SYNC_SALES = 50;

    public function __construct(
        private StockService $stockService,
        private ReceiptService $receiptService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            abort(403, 'User is not assigned to a branch.');
        }

        $location = Location::query()
            ->where('branch_id', $branch->id)
            ->where('type', 'branch')
            ->first();

        $categories = Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $systemCurrency = $branch->tenant->currency ?? 'USD';

        // Get exchange rates for this tenant
        $exchangeRates = ExchangeRate::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(fn ($rate) => ["{$rate->from_currency}_{$rate->to_currency}" => (float) $rate->rate]);

        // Get held orders for this branch
        $heldOrders = HeldOrder::where('branch_id', $branch->id)
            ->where('cashier_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'name' => $order->name,
                'items' => $order->items,
                'total' => (float) $order->total,
                'created_at' => $order->created_at->format('H:i'),
            ]);

        $customers = Customer::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'phone']);

        return Inertia::render('POS/Index', [
            'branch' => [
                'id' => $branch->id,
                'name' => $branch->name,
            ],
            'categories' => $categories,
            'currency' => $systemCurrency,
            'locationId' => $location?->id,
            'exchangeRates' => $exchangeRates,
            'heldOrders' => $heldOrders,
            'customers' => $customers,
            'taxRate' => (float) ($user->tenant?->tax_rate ?? 0),
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            return response()->json(['error' => 'User is not assigned to a branch.'], 403);
        }

        $location = Location::query()
            ->where('branch_id', $branch->id)
            ->where('type', 'branch')
            ->first();

        $query = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['category:id,name', 'media', 'sellUnits.unit:id,name,abbreviation']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', $search)
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $products = $query->orderBy('name')->take(25)->get();

        // Get stock levels for all products
        $productIds = $products->pluck('id')->toArray();
        $stockLevels = $location
            ? $this->stockService->getStockForProducts($productIds, $location)
            : collect();

        $products = $products->map(function ($product) use ($stockLevels) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'barcode' => $product->barcode,
                'sku' => $product->sku,
                'price' => $product->getSellingPrice(),
                'convert_price' => $product->getSellingPriceIn('USD'),
                'cost_price' => (float) $product->cost_price,
                'currency' => $product->currency,
                'unlimited_quantity' => $product->unlimited_quantity,
                'unit' => $product->unit,
                'category_id' => $product->category_id,
                'category_name' => $product->category?->name,
                'image_url' => $product->image_url,
                'stock' => $stockLevels->get($product->id, 0),
                'sell_units' => $product->sellUnits->map(fn ($pu) => [
                    'id' => $pu->unit_id,
                    'name' => $pu->unit->name,
                    'abbreviation' => $pu->unit->abbreviation,
                    'to_base_factor' => (float) $pu->to_base_factor,
                    'is_base' => $pu->is_base,
                ]),
            ];
        });

        return response()->json([
            'products' => $products,
        ]);
    }

    public function previewDiscounts(Request $request, CartPricing $pricing): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'items' => ['present', 'array', 'max:200'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id,tenant_id,'.$user->tenant_id],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001', 'max:1000000'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id,tenant_id,'.$user->tenant_id],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'payment_type' => ['nullable', 'in:cash,debt'],
        ]);

        return response()->json($pricing->preview(
            $user,
            $data['items'],
            $data['customer_id'] ?? null,
            $data['coupon_code'] ?? null,
            $data['payment_type'] ?? 'cash',
        ));
    }

    public function checkout(CheckoutRequest $request, CheckoutAction $checkoutAction): JsonResponse
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            return response()->json(['error' => 'User is not assigned to a branch.'], 403);
        }

        try {
            $order = $checkoutAction->execute(
                branch: $branch,
                cartItems: $request->input('items'),
                cashReceived: (float) $request->input('cash_received'),
                cashier: $user,
                discountType: $request->input('discount_type'),
                discountValue: (float) $request->input('discount_value', 0),
                customerId: $request->input('customer_id') ? (int) $request->input('customer_id') : null,
                paymentType: $request->input('payment_type') ?? 'cash',
                offlineId: $request->input('offline_id'),
                couponCode: $request->input('coupon_code'),
            );

            return response()->json([
                'success' => true,
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => (float) $order->subtotal,
                    'discount_amount' => (float) $order->discount_amount,
                    'total' => (float) $order->total,
                    'change' => (float) $order->payment->change_amount,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function receipt(Order $order): JsonResponse
    {
        $user = request()->user();

        // Ensure user can only view receipts from their branch
        if ($order->branch_id !== $user->branch_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $receiptData = $this->receiptService->generateReceiptData($order);

        return response()->json($receiptData);
    }

    public function syncOfflineSales(Request $request, CheckoutAction $checkoutAction): JsonResponse
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            return response()->json(['error' => 'User is not assigned to a branch.'], 403);
        }

        $request->validate([
            'sales' => ['present', 'array', 'max:'.self::MAX_SYNC_SALES],
        ]);

        return response()->json(app(SyncOfflineSalesAction::class)->execute($user, $branch, $request->input('sales', [])));
    }

    public function holdOrder(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            return response()->json(['error' => 'User is not assigned to a branch.'], 403);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.name' => ['required', 'string'],
            'items.*.price' => ['required', 'numeric'],
            'items.*.price_in_system_currency' => ['required', 'numeric'],
            'items.*.currency' => ['required', 'string'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'total' => ['required', 'numeric'],
        ]);

        $heldOrder = app(HoldOrderAction::class)->execute($user, $branch, $validated);

        return response()->json([
            'success' => true,
            'held_order' => [
                'id' => $heldOrder->id,
                'name' => $heldOrder->name,
                'items' => $heldOrder->items,
                'total' => (float) $heldOrder->total,
                'created_at' => $heldOrder->created_at->format('H:i'),
            ],
        ]);
    }

    public function retrieveHeldOrder(HeldOrder $heldOrder): JsonResponse
    {
        $user = request()->user();

        if ($heldOrder->branch_id !== $user->branch_id || $heldOrder->cashier_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'held_order' => [
                'id' => $heldOrder->id,
                'name' => $heldOrder->name,
                'items' => $heldOrder->items,
                'total' => (float) $heldOrder->total,
            ],
        ]);
    }

    public function deleteHeldOrder(HeldOrder $heldOrder): JsonResponse
    {
        $user = request()->user();

        if ($heldOrder->branch_id !== $user->branch_id || $heldOrder->cashier_id !== $user->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $heldOrder->delete();

        return response()->json(['success' => true]);
    }

    public function getHeldOrders(Request $request): JsonResponse
    {
        $user = $request->user();
        $branch = $user->branch;

        if (! $branch) {
            return response()->json(['error' => 'User is not assigned to a branch.'], 403);
        }

        $heldOrders = HeldOrder::where('branch_id', $branch->id)
            ->where('cashier_id', $user->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($order) => [
                'id' => $order->id,
                'name' => $order->name,
                'items' => $order->items,
                'total' => (float) $order->total,
                'created_at' => $order->created_at->format('H:i'),
            ]);

        return response()->json(['held_orders' => $heldOrders]);
    }
}
