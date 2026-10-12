<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreProductRequest;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductUnit;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Product::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['category:id,name', 'media']);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $products = $query->orderBy('name')->paginate(20)->withQueryString();

        $categories = Category::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Products/Index', [
            'products' => $products,
            'categories' => $categories,
            'filters' => $request->only(['search', 'category_id', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $categories = Category::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = Unit::forTenant($user->tenant_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'tenant_id']);

        return Inertia::render('Products/Create', [
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $user = $request->user();

        $validated = collect($request->validated())->except('image')->all();
        $validated['tenant_id'] = $user->tenant_id;
        $validated['cost_price'] ??= 0;

        $product = Product::create($validated);

        if ($request->hasFile('image')) {
            $product->addMediaFromRequest('image')->toMediaCollection('images');
        }

        // Sync product units
        if ($request->has('units')) {
            $this->syncProductUnits($product, $request->input('units', []));
        }

        return redirect()->route('products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(Request $request, Product $product): Response
    {
        $user = $request->user();

        if ($product->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $categories = Category::where('tenant_id', $user->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $units = Unit::forTenant($user->tenant_id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'abbreviation', 'tenant_id']);

        return Inertia::render('Products/Edit', [
            'product' => $product->load(['media', 'productUnits.unit']),
            'categories' => $categories,
            'units' => $units,
        ]);
    }

    public function update(StoreProductRequest $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if ($product->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $validated = $request->validated();
        $product->update($validated);

        if ($request->hasFile('image')) {
            $product->clearMediaCollection('images');
            $product->addMediaFromRequest('image')->toMediaCollection('images');
        }

        // Sync product units
        if ($request->has('units')) {
            $this->syncProductUnits($product, $request->input('units', []));
        }

        return redirect()->route('products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        if ($product->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Product deleted successfully.');
    }

    /**
     * Sync product units from the request payload.
     *
     * @param  array<array{unit_id: int, to_base_factor: float, allowed_for: string, is_base: bool}>  $units
     */
    private function syncProductUnits(Product $product, array $units): void
    {
        // Collect incoming unit IDs so we can delete removed ones
        $incomingUnitIds = collect($units)->pluck('unit_id')->filter()->toArray();

        // Delete product units that are no longer in the list
        $product->productUnits()
            ->whereNotIn('unit_id', $incomingUnitIds)
            ->delete();

        foreach ($units as $unitData) {
            ProductUnit::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'unit_id' => $unitData['unit_id'],
                ],
                [
                    'to_base_factor' => $unitData['to_base_factor'],
                    'allowed_for' => $unitData['allowed_for'],
                    'is_base' => $unitData['is_base'] ?? false,
                ],
            );
        }
    }
}
