<?php

namespace App\Modules\Inventory\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Services\UnitConversionService;
use App\Modules\Company\Models\ExchangeRate;
use App\Modules\Company\Models\Location;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Inventory\Actions\CreatePurchaseAction;
use App\Modules\Inventory\Actions\MarkPurchaseReceivedAction;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PurchaseController extends Controller
{
    public function __construct(private UnitConversionService $unitConversionService, private BranchAccess $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Purchase::query()
            ->where('company_id', $user->company_id)
            ->with(['supplier:id,name', 'location:id,name', 'creator:id,name']);

        $branchId = $this->access->effective($user, $request->input('branch_id'));

        if ($branchId !== null) {
            $query->whereHas('location', fn ($location) => $location->where('branch_id', $branchId));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $purchases = $query->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $suppliers = Supplier::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases,
            'suppliers' => $suppliers,
            'filters' => [...$request->only(['status', 'supplier_id']), 'branch_id' => $this->access->canSeeAll($user) ? $branchId : null],
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();
        $company = $user->company;

        $suppliers = Supplier::where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $locations = $this->access->scope(
            Location::where('company_id', $user->company_id)->where('is_active', true),
            $user,
        )->orderBy('name')->get(['id', 'name', 'type']);

        $products = Product::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->with(['buyUnits.unit:id,name,abbreviation'])
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'cost_price', 'currency']);

        $exchangeRates = ExchangeRate::where('company_id', $user->company_id)
            ->where('is_active', true)
            ->get(['from_currency', 'to_currency', 'rate'])
            ->map(fn (ExchangeRate $rate) => [
                'from' => $rate->from_currency,
                'to' => $rate->to_currency,
                'rate' => (float) $rate->rate,
            ]);

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'locations' => $locations,
            'products' => $products,
            'companyCurrency' => $company->currency ?? 'USD',
            'exchangeRates' => $exchangeRates,
            'availableCurrencies' => [
                ['code' => 'USD', 'name' => 'US Dollar'],
                ['code' => 'EUR', 'name' => 'Euro'],
                ['code' => 'GBP', 'name' => 'British Pound'],
                ['code' => 'LBP', 'name' => 'Lebanese Pound'],
                ['code' => 'AED', 'name' => 'UAE Dirham'],
                ['code' => 'SAR', 'name' => 'Saudi Riyal'],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $user->company_id);

        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', $inCompany('suppliers')],
            'location_id' => ['required', 'integer', $inCompany('locations')],
            'currency' => ['required', 'string', 'size:3'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', $inCompany('products')],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where(
                fn ($query) => $query->where('company_id', $user->company_id)->orWhereNull('company_id')
            )],
        ]);

        $this->access->ensure($user, Location::query()->whereKey($validated['location_id'])->value('branch_id'));

        app(CreatePurchaseAction::class)->execute($user, $validated);

        return redirect()->route('purchases.index')
            ->with('success', 'Purchase order created successfully.');
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $user = $request->user();

        if ($purchase->company_id !== $user->company_id) {
            abort(403);
        }

        $this->access->ensure($user, $purchase->location()->value('branch_id'));

        try {
            app(MarkPurchaseReceivedAction::class)->execute($purchase, $user);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('purchases.index')->with('error', $e->getMessage());
        }

        return redirect()->route('purchases.index')
            ->with('success', 'Purchase received and stock updated.');
    }
}
