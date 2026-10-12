<?php

namespace App\Modules\Tenant\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Enums\LocationType;
use App\Modules\Tenant\Http\Requests\StoreLocationRequest;
use App\Modules\Tenant\Models\Location;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Core\Contracts\LocationUsage;

class LocationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('manage locations')) {
            abort(403);
        }

        $locations = Location::query()
            ->where('type', LocationType::Warehouse)
            ->where('tenant_id', $user->tenant_id)
            ->with('branch:id,name')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Locations/Index', [
            'locations' => $locations,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('manage locations')) {
            abort(403);
        }

        return Inertia::render('Locations/Create', [
            'locationTypes' => array_map(fn ($case) => [
                'value' => $case->value,
                'label' => ucfirst(str_replace('_', ' ', $case->name)),
            ], LocationType::cases()),
        ]);
    }

    public function store(StoreLocationRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('manage locations')) {
            abort(403);
        }
        $validated = $request->validated();

        $validated['tenant_id'] = $user->tenant_id;
        Location::create($validated + [
            'type' => LocationType::Warehouse,
        ]);

        return redirect()->route('locations.index')
            ->with('success', 'Location created successfully.');
    }

    public function edit(Request $request, Location $location): Response
    {
        $user = $request->user();

        if ($location->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        return Inertia::render('Locations/Edit', [
            'location' => $location,
            'locationTypes' => array_map(fn ($case) => [
                'value' => $case->value,
                'label' => ucfirst(str_replace('_', ' ', $case->name)),
            ], LocationType::cases()),
        ]);
    }

    public function update(StoreLocationRequest $request, Location $location): RedirectResponse
    {
        $user = $request->user();

        if ($location->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $validated = $request->validated();
        $location->update($validated);

        return redirect()->route('locations.index')
            ->with('success', 'Location updated successfully.');
    }

    public function destroy(Request $request, Location $location): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('manage locations')) {
            abort(403);
        }

        if ($location->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        if (app(LocationUsage::class)->inUse($location->id)) {
            return redirect()->route('locations.index')
                ->with('error', 'Cannot delete location with stock movements.');
        }

        $location->delete();

        return redirect()->route('locations.index')
            ->with('success', 'Location deleted successfully.');
    }
}
