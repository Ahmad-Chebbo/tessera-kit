<?php

namespace Tessera\Core\Http;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tessera\Engine\Navigation;

/**
 * Where people land after signing in when the project has no dashboard: the first page their menu offers.
 */
class HomeController
{
    public function __invoke(Request $request, Navigation $navigation): RedirectResponse
    {
        $user = $request->user();
        $permissions = $user->getAllPermissions()->pluck('name')->all();

        foreach ($navigation->build($request, $permissions) as $entry) {
            $href = $entry['href'] ?? ($entry['items'][0]['href'] ?? null);

            if ($href !== null) {
                return redirect($href);
            }
        }

        return redirect()->route('profile.edit');
    }
}
