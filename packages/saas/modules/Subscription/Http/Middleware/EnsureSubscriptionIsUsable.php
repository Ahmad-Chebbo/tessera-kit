<?php

namespace App\Modules\Subscription\Http\Middleware;

use App\Modules\Subscription\Services\Entitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionIsUsable
{
    public function __construct(private Entitlements $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->company_id === null || $user->isSuperAdmin() || $this->entitlements->isUsable($user->company_id)) {
            return $next($request);
        }

        if ($request->routeIs('subscription.*', 'logout', 'profile.*')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Your subscription is not active.'], 403);
        }

        return redirect()->route('subscription.expired');
    }
}
