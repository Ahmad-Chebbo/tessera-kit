<?php

namespace App\Modules\Customers\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Core\Contracts\CustomerActivity;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $query = Customer::query()
            ->where('tenant_id', $user->tenant_id);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->input('search').'%')
                    ->orWhere('phone', 'like', '%'.$request->input('search').'%')
                    ->orWhere('email', 'like', '%'.$request->input('search').'%');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $customers = $query->orderBy('name')->paginate(20)->withQueryString();

        $activity = app(CustomerActivity::class);
        $totals = $activity->totals($customers->pluck('id')->all());

        $customers->through(function (Customer $customer) use ($totals): Customer {
            $customer->setAttribute('orders_count', $totals[$customer->id]['orders'] ?? 0);
            $customer->setAttribute('total_debt', $totals[$customer->id]['debt'] ?? 0);

            return $customer;
        });

        return Inertia::render('Customers/Index', [
            'ordersAvailable' => $activity->available(),
            'customers' => $customers,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Customers/Create', ['groups' => $this->groups($request)]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);

        $customer = Customer::create([
            ...$validated,
            'tenant_id' => $request->user()->tenant_id,
        ]);

        if ($request->expectsJson()) {
            return response()->json(['customer' => $customer]);
        }

        return redirect()->route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(Request $request, Customer $customer): Response
    {

        $user = $request->user();

        if ($customer->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $activity = app(CustomerActivity::class);
        $summary = $activity->summary($customer->id);

        $stats = [
            'totalOrders' => $summary['orders'],
            'totalSpent' => $summary['spent'],
            'totalDebt' => $summary['debt'],
            'paidDebt' => $summary['paid_debt'],
        ];

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'orders' => $activity->orders($customer->id),
            'stats' => $stats,
            'ordersAvailable' => $activity->available(),
        ]);
    }

    public function edit(Request $request, Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer,
            'groups' => $this->groups($request),
        ]);
    }

    /**
     * @return Collection<int, CustomerGroup>
     */
    private function groups(Request $request)
    {
        return CustomerGroup::query()->where('tenant_id', $request->user()->tenant_id)->orderBy('name')->get(['id', 'name']);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        if ($customer->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'customer_group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);

        $customer->update($validated);

        return redirect()->route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Request $request, Customer $customer): RedirectResponse
    {
        if ($customer->tenant_id !== $request->user()->tenant_id) {
            abort(403);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', 'Customer deleted successfully.');
    }
}
