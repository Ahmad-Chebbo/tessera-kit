<?php

namespace App\Modules\Company\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Company\Enums\LocationType;
use App\Modules\Company\Http\Requests\StoreBranchRequest;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Location;
use App\Modules\Subscription\Services\Entitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BranchController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        if (! $user->can('manage branches')) {
            abort(403);
        }

        $branches = Branch::query()
            ->where('company_id', $user->company_id)
            ->withCount('users')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function create(Request $request): Response
    {
        if (! $request->user()->can('manage branches')) {
            abort(403);
        }

        return Inertia::render('Branches/Create');
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('manage branches')) {
            abort(403);
        }
        if (! app(Entitlements::class)->hasRoomFor($user->company_id, 'branches')) {
            return back()->with('error', 'Your plan has reached its branch limit.');
        }

        $validated = $request->validated();
        $validated['company_id'] = $user->company_id;
        $branch = Branch::create($validated);

        Location::create([
            'company_id' => Auth::user()->company_id,
            'branch_id' => $branch->id,
            'name' => $branch->name,
            'type' => LocationType::Branch,
        ]);

        return redirect()->route('branches.index')
            ->with('success', 'Branch created successfully.');
    }

    public function edit(Request $request, Branch $branch): Response
    {
        $user = $request->user();

        if (! $user->can('manage branches')) {
            abort(403);
        }

        if ($branch->company_id !== $user->company_id) {
            abort(403);
        }

        return Inertia::render('Branches/Edit', [
            'branch' => $branch,
        ]);
    }

    public function update(StoreBranchRequest $request, Branch $branch): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('manage branches')) {
            abort(403);
        }

        if ($branch->company_id !== $user->company_id) {
            abort(403);
        }

        $validated = $request->validated();
        $branch->update($validated);
        // Update the associated location as well
        $location = $branch->location;
        if ($location) {
            $location->update([
                'name' => $branch->name,
            ]);
        }

        return redirect()->route('branches.index')
            ->with('success', 'Branch updated successfully.');
    }

    public function destroy(Request $request, Branch $branch): RedirectResponse
    {
        $user = $request->user();

        if (! $user->can('manage branches')) {
            abort(403);
        }

        if ($branch->company_id !== $user->company_id) {
            abort(403);
        }

        if ($branch->users()->count() > 0) {
            return redirect()->route('branches.index')
                ->with('error', 'Cannot delete branch with assigned users.');
        }

        $branch->delete();

        return redirect()->route('branches.index')
            ->with('success', 'Branch deleted successfully.');
    }
}
