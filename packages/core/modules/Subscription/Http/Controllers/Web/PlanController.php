<?php

namespace App\Modules\Subscription\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Http\Requests\StorePlanRequest;
use App\Modules\Subscription\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Engine\ModuleRegistry;

class PlanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Plans/Index', [
            'plans' => Plan::query()->withCount('subscriptions')->orderBy('sort_order')->orderBy('price')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Plans/Edit', ['plan' => null, 'modules' => $this->modules()]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::create($this->attributes($request));

        return redirect()->route('admin.plans.index')->with('success', 'Plan created.');
    }

    public function edit(Plan $plan): Response
    {
        return Inertia::render('Admin/Plans/Edit', ['plan' => $plan, 'modules' => $this->modules()]);
    }

    public function update(StorePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($this->attributes($request));

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated.');
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->subscriptions()->exists()) {
            return back()->with('error', 'This plan has subscriptions. Deactivate it instead.');
        }

        $plan->delete();

        return back()->with('success', 'Plan deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StorePlanRequest $request): array
    {
        return [...$request->validated(), 'is_active' => $request->boolean('is_active'), 'sort_order' => $request->integer('sort_order')];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function modules(): array
    {
        return app(ModuleRegistry::class)->featureOptions();
    }
}
