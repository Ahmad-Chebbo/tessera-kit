<?php

namespace App\Http\Middleware;

use App\Modules\Team\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;
use Tessera\Core\Contracts\PlanLimits;
use Tessera\Engine\ModuleRegistry;
use Tessera\Engine\Navigation;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Inertia only serves requests when the web layer of the modules is on.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(ModuleRegistry::class)->webEnabled()) {
            return $next($request);
        }

        return parent::handle($request, $next);
    }

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    private function permissionIsAvailable(User $user, string $permission): bool
    {
        $module = app(ModuleRegistry::class)->featureForPermission($permission);

        return $module === null
            || $user->isSuperAdmin()
            || app(PlanLimits::class)->hasModule($user->tenant_id, $module);
    }

    /**
     * Define the props that are shared by default. Modules add theirs (the plan, the branches, the currency...).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user) {
            $user->load('roles');
        }

        $flash = array_filter([
            'success' => $request->session()->get('success'),
            'error' => $request->session()->get('error'),
        ]);

        if ($flash !== []) {
            Inertia::flash($flash);
        }

        $permissions = $user ? $user->getAllPermissions()->pluck('name')->filter(fn (string $name): bool => $this->permissionIsAvailable($user, $name))->values()->toArray() : [];

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'tenant_id' => $user->tenant_id,
                    'branch_id' => $user->branch_id,
                    'roles' => $user->roles->map(fn ($role) => ['id' => $role->id, 'name' => $role->name]),
                ] : null,
                'permissions' => $permissions,
                'currentBranch' => $user ? $user->branch?->name : null,
            ],
            'enabledModules' => fn () => array_keys(app(ModuleRegistry::class)->enabled()),
            'navigation' => fn () => $user ? app(Navigation::class)->build($request, $permissions) : [],
            ...array_map(fn (Closure $resolver): Closure => fn () => $resolver($request, $user), app(ModuleRegistry::class)->sharedProps()),
        ];
    }
}
