<?php

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\UnitConversionService;
use App\Modules\Inventory\Actions\CreateAdjustmentAction;
use App\Modules\Inventory\Actions\TransferStockAction;
use App\Modules\Inventory\Enums\StockMovementType;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockService;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function __construct(
        private StockService $stockService,
        private CreateAdjustmentAction $adjustmentAction,
        private UnitConversionService $unitConversionService,
        private BranchAccess $access,
    ) {}

    /**
     * The tenant's locations the user may work with, narrowed to one branch when a branch is chosen or enforced.
     *
     * @return Builder<Location>
     */
    private function locationsFor(Request $request, bool $activeOnly = true): Builder
    {
        $user = $request->user();

        return $this->access->scope(
            Location::query()
                ->where('tenant_id', $user->tenant_id)
                ->when($activeOnly, fn ($query) => $query->where('is_active', true)),
            $user,
            $request->input('branch_id'),
        );
    }

    private function branchFilter(Request $request): ?int
    {
        $user = $request->user();

        return $this->access->canSeeAll($user) ? $this->access->effective($user, $request->input('branch_id')) : null;
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $locations = $this->locationsFor($request)->get(['id', 'name', 'type']);
        $locationIds = $locations->pluck('id')->all();

        $selectedLocationId = $request->input('location_id');

        $query = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['category:id,name']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        // Get stock levels for products
        if ($selectedLocationId && $selectedLocationId != 'all') {
            $location = $locations->firstWhere('id', (int) $selectedLocationId);
            $productIds = $products->pluck('id')->toArray();
            $stockLevels = $location
                ? $this->stockService->getStockForProducts($productIds, $location)
                : collect();

            $products->getCollection()->transform(function ($product) use ($stockLevels) {
                $product->stock = $stockLevels->get($product->id, 0);

                return $product;
            });
        } else {
            $productIds = $products->pluck('id')->toArray();
            $stockLevels = $this->stockService->getStockForProducts($productIds, null, $locationIds);

            $products->getCollection()->transform(function ($product) use ($stockLevels) {
                $product->stock = $stockLevels->get($product->id, 0);

                return $product;
            });
        }

        return Inertia::render('Inventory/Index', [
            'products' => $products,
            'locations' => $locations,
            'selectedLocationId' => $selectedLocationId,
            'filters' => [...$request->only(['search', 'location_id']), 'branch_id' => $this->branchFilter($request)],
        ]);
    }

    public function movements(Request $request): Response
    {
        $user = $request->user();

        $query = StockMovement::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['product:id,name', 'location:id,name', 'creator:id,name']);

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $locations = $this->locationsFor($request, false)->get(['id', 'name']);
        $query->whereIn('location_id', $locations->pluck('id'));

        if ($request->filled('location_id')) {
            $query->where('location_id', $request->input('location_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $movements = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $movementTypes = collect(StockMovementType::cases())->map(fn ($type) => [
            'value' => $type->value,
            'label' => ucfirst(str_replace('_', ' ', $type->value)),
        ]);

        return Inertia::render('Inventory/Movements', [
            'movements' => $movements,
            'locations' => $locations,
            'movementTypes' => $movementTypes,
            'filters' => [...$request->only(['product_id', 'location_id', 'type', 'date_from', 'date_to']), 'branch_id' => $this->branchFilter($request)],
        ]);
    }

    public function adjust(Request $request): Response
    {
        $user = $request->user();

        $products = Product::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['productUnits.unit:id,name,abbreviation'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $locations = $this->locationsFor($request)->get(['id', 'name', 'type']);

        return Inertia::render('Inventory/Adjust', [
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    public function storeAdjustment(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'location_id' => ['required', 'exists:locations,id'],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $product = Product::findOrFail($validated['product_id']);
        $location = Location::findOrFail($validated['location_id']);

        if ($product->tenant_id !== $user->tenant_id || $location->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $this->access->ensure($user, $location->branch_id);

        $this->adjustmentAction->execute(
            product: $product,
            location: $location,
            quantity: (float) $validated['quantity'],
            user: $user,
            notes: $validated['notes'] ?? null,
            unitId: $validated['unit_id'] ?? null,
        );

        return redirect()->route('inventory.index')
            ->with('success', 'Stock adjusted successfully.');
    }

    public function transfer(Request $request): Response
    {
        $user = $request->user();

        $products = Product::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->with(['productUnits.unit:id,name,abbreviation'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku']);

        $locations = $this->locationsFor($request)->get(['id', 'name', 'type']);

        return Inertia::render('Inventory/Transfer', [
            'products' => $products,
            'locations' => $locations,
        ]);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'from_location_id' => ['required', 'exists:locations,id', 'different:to_location_id'],
            'to_location_id' => ['required', 'exists:locations,id'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_id' => ['nullable', 'exists:units,id'],
        ]);

        $fromLocation = Location::findOrFail($validated['from_location_id']);
        $toLocation = Location::findOrFail($validated['to_location_id']);

        if ($fromLocation->tenant_id !== $user->tenant_id || $toLocation->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $this->access->ensure($user, $fromLocation->branch_id);
        $this->access->ensure($user, $toLocation->branch_id);

        app(TransferStockAction::class)->execute($user, $fromLocation, $toLocation, $validated['items'], $validated['notes'] ?? null);

        return redirect()->route('inventory.index')
            ->with('success', 'Stock transferred successfully.');
    }
}
