<?php

namespace App\Modules\Subscription\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Actions\CreateTenantAction;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $query = Tenant::query()
            ->withCount(['branches', 'users'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $tenants = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        return Inertia::render('Admin/Tenants/Create', [
            'plans' => Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('price')->get(['id', 'name', 'price', 'currency', 'billing_interval', 'trial_days']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'max:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            // Admin user details
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', Password::defaults()],
        ]);

        app(CreateTenantAction::class)->execute($validated);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant created successfully.');
    }

    public function edit(Request $request, Tenant $tenant): Response
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        return Inertia::render('Admin/Tenants/Edit', [
            'tenant' => $tenant,
        ]);
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', 'string', 'max:3'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $tenant->update($validated);

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant updated successfully.');
    }

    public function destroy(Request $request, Tenant $tenant): RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        // Check if tenant has users
        if ($tenant->users()->count() > 0) {
            return redirect()->route('admin.tenants.index')
                ->with('error', 'Cannot delete tenant with existing users.');
        }

        $tenant->delete();

        return redirect()->route('admin.tenants.index')
            ->with('success', 'Tenant deleted successfully.');
    }

    public function show(Request $request, Tenant $tenant): Response
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403);
        }

        $tenant->load(['branches', 'users.roles']);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $tenant,
        ]);
    }
}
