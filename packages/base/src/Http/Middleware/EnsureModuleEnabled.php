<?php

namespace Tessera\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Engine\ModuleRegistry;

/**
 * For routes that no permission guards. Permission-guarded routes are covered by the Gate in the User model. Without the
 * Subscription module nothing is limited, so every module is enabled.
 */
class EnsureModuleEnabled
{
    public function __construct(private PlanLimits $limits) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();
        $label = app(ModuleRegistry::class)->features()[$module]->label ?? $module;

        if ($user === null || $user->isSuperAdmin() || $this->limits->hasModule($user->tenant_id, $module)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => "Your plan does not include {$label}."], 403);
        }

        return redirect()->route('subscription.show')->with('error', "Your plan does not include {$label}.");
    }
}
