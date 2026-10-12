<?php

namespace App\Modules\Customers\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Customers\Models\CustomerGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CustomerGroupController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Customers/Groups', [
            'groups' => CustomerGroup::query()
                ->where('tenant_id', $request->user()->tenant_id)
                ->withCount('customers')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        CustomerGroup::create([...$this->validated($request), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Customer group created.');
    }

    public function update(Request $request, CustomerGroup $group): RedirectResponse
    {
        $this->ensureOwned($request, $group);
        $group->update($this->validated($request, $group));

        return back()->with('success', 'Customer group updated.');
    }

    public function destroy(Request $request, CustomerGroup $group): RedirectResponse
    {
        $this->ensureOwned($request, $group);
        $group->delete();

        return back()->with('success', 'Customer group deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CustomerGroup $group = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('customer_groups', 'name')->where('tenant_id', $request->user()->tenant_id)->ignore($group?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function ensureOwned(Request $request, CustomerGroup $group): void
    {
        abort_unless($group->tenant_id === $request->user()->tenant_id, 403);
    }
}
