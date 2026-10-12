<?php

namespace App\Modules\Company\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Company\Enums\LocationType;
use App\Modules\Company\Http\Requests\StoreLocationRequest;
use App\Modules\Company\Models\Location;
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
            ->where('company_id', $user->company_id)
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

        $validated['company_id'] = $user->company_id;
        Location::create($validated + [
            'type' => LocationType::Warehouse,
        ]);

        return redirect()->route('locations.index')
            ->with('success', 'Location created successfully.');
    }

    public function edit(Request $request, Location $location): Response
    {
        $user = $request->user();

        if ($location->company_id !== $user->company_id) {
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

        if ($location->company_id !== $user->company_id) {
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

        if ($location->company_id !== $user->company_id) {
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
