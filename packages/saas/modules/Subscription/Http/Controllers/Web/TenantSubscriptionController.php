<?php

namespace App\Modules\Subscription\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Modules\Subscription\Http\Requests\UpdateTenantSubscriptionRequest;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Subscription\Services\SubscriptionService;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Engine\ModuleRegistry;

class TenantSubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subscriptions) {}

    public function edit(Tenant $tenant): Response
    {
        $subscription = $tenant->subscriptions()->with('plan')->latest('id')->first();

        return Inertia::render('Admin/Tenants/Subscription', [
            'tenant' => $tenant->only(['id', 'name']),
            'subscription' => $subscription ? [
                'plan_id' => $subscription->plan_id,
                'status' => $subscription->status->value,
                'effective_status' => $subscription->effectiveStatus()->value,
                'trial_ends_at' => $subscription->trial_ends_at?->format('Y-m-d\TH:i'),
                'ends_at' => $subscription->ends_at?->format('Y-m-d\TH:i'),
                'module_overrides' => $subscription->module_overrides ?? (object) [],
                'notes' => $subscription->notes,
            ] : null,
            'plans' => Plan::query()->orderBy('sort_order')->orderBy('price')->get(['id', 'name', 'modules', 'is_active']),
            'modules' => app(ModuleRegistry::class)->featureOptions(),
            'statuses' => array_column(SubscriptionStatus::cases(), 'value'),
            'history' => $tenant->subscriptions()->with('plan:id,name')->latest('id')->get()->map(fn (Subscription $row): array => [
                'id' => $row->id,
                'plan' => $row->plan->name,
                'status' => $row->status->value,
                'starts_at' => $row->starts_at?->toIso8601String(),
                'ends_at' => $row->ends_at?->toIso8601String(),
            ]),
        ]);
    }

    public function update(UpdateTenantSubscriptionRequest $request, Tenant $tenant): RedirectResponse
    {
        $current = $tenant->subscriptions()->latest('id')->first();
        $plan = Plan::findOrFail($request->validated('plan_id'));

        $changedPlan = $current === null || $current->plan_id !== $plan->id;
        $subscription = $changedPlan ? $this->subscriptions->start($tenant, $plan) : $current;

        $subscription->update([
            'status' => $request->validated('status'),
            'trial_ends_at' => $request->validated('trial_ends_at'),
            'ends_at' => $request->validated('ends_at'),
            'module_overrides' => $request->overrides() ?: null,
            'notes' => $request->validated('notes'),
            'canceled_at' => $request->validated('status') === SubscriptionStatus::Canceled->value ? ($subscription->canceled_at ?? now()) : null,
        ]);

        return redirect()->route('admin.tenants.subscription.edit', $tenant)->with('success', 'Subscription updated.');
    }
}
