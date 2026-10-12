<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnitController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $units = Unit::query()
            ->forTenant($user->tenant_id)
            ->active()
            ->orderByRaw('tenant_id IS NULL DESC') // Global first
            ->orderBy('name')
            ->get();

        return Inertia::render('Units/Index', [
            'units' => $units,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:10'],
        ]);

        Unit::create([
            'tenant_id' => $user->tenant_id,
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
        ]);

        return redirect()->route('units.index')
            ->with('success', 'Unit created successfully.');
    }

    public function update(Request $request, Unit $unit): RedirectResponse
    {
        $user = $request->user();

        // Cannot edit global units
        if ($unit->isGlobal()) {
            return redirect()->route('units.index')
                ->with('error', 'Cannot edit system units.');
        }

        if ($unit->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'abbreviation' => ['required', 'string', 'max:10'],
        ]);

        $unit->update($validated);

        return redirect()->route('units.index')
            ->with('success', 'Unit updated successfully.');
    }

    public function destroy(Request $request, Unit $unit): RedirectResponse
    {
        $user = $request->user();

        if ($unit->isGlobal()) {
            return redirect()->route('units.index')
                ->with('error', 'Cannot delete system units.');
        }

        if ($unit->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        // Check if unit is in use by any product
        if ($unit->productUnits()->exists()) {
            return redirect()->route('units.index')
                ->with('error', 'Cannot delete a unit that is assigned to products.');
        }

        $unit->delete();

        return redirect()->route('units.index')
            ->with('success', 'Unit deleted successfully.');
    }
}
