<?php

namespace App\Modules\Subscription\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Subscription\Services\Entitlements;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Tessera\Engine\ModuleRegistry;

class SubscriptionController extends Controller
{
    public function __construct(private Entitlements $entitlements) {}

    public function show(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;
        $subscription = $this->entitlements->subscription($companyId);
        $enabled = $this->entitlements->modules($companyId);

        return Inertia::render('Subscription/Show', [
            'subscription' => $subscription ? [
                'plan' => $subscription->plan->only(['name', 'description', 'price', 'currency', 'billing_interval']),
                'status' => $subscription->effectiveStatus()->value,
                'usable' => $subscription->isUsable(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
            ] : null,
            'modules' => collect(app(ModuleRegistry::class)->featureOptions())->map(fn (array $feature): array => [...$feature, 'enabled' => in_array($feature['value'], $enabled, true)])->all(),
            'usage' => [
                'users' => ['used' => $this->entitlements->usage($companyId, 'users'), 'limit' => $this->entitlements->limit($companyId, 'users')],
                'branches' => ['used' => $this->entitlements->usage($companyId, 'branches'), 'limit' => $this->entitlements->limit($companyId, 'branches')],
            ],
        ]);
    }

    public function expired(Request $request): Response
    {
        $subscription = $this->entitlements->subscription((int) $request->user()->company_id);

        return Inertia::render('Subscription/Expired', [
            'plan' => $subscription?->plan->name,
            'status' => $subscription?->effectiveStatus()->value,
        ]);
    }
}
