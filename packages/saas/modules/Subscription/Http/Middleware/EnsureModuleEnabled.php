<?php

namespace App\Modules\Subscription\Http\Middleware;

use App\Modules\Subscription\Services\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tessera\Engine\ModuleRegistry;

/**
 * For routes that no permission guards. Permission-guarded routes are covered by the Gate in AppServiceProvider.
 */
class EnsureModuleEnabled
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        $label = app(ModuleRegistry::class)->features()[$module]->label ?? $module;

        if ($user === null || $user->tenant_id === null || $user->isSuperAdmin() || $this->entitlements->hasModule($user->tenant_id, $module)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => "Your plan does not include {$label}."], 403);
        }

        return redirect()->route('subscription.show')->with('error', "Your plan does not include {$label}.");
    }
}
