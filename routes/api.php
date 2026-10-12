<?php

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Route;
use Tessera\Engine\ModuleRegistry;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| Each module owns its API routes in app/Modules/<Module>/Routes/api.php (signed in with a Sanctum token) and
| Routes/api-guest.php (open, rate limited), served by controllers in Http/Controllers/Api/V1 and shaped by
| Http/Resources. The module registry loads them here under /api/v1 when the api layer is on.
|
*/

$modules = app(ModuleRegistry::class);

if ($modules->apiEnabled()) {
    Route::prefix('v1')->name('api.v1.')->middleware('throttle:10,1')->group(function () use ($modules) {
        foreach ($modules->routeFiles('api-guest.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });

    $signedIn = ['auth:sanctum', EnsureUserIsActive::class, ...(array_key_exists('subscription.usable', Route::getMiddleware()) ? ['subscription.usable'] : [])];

    Route::prefix('v1')->name('api.v1.')->middleware($signedIn)->group(function () use ($modules) {
        foreach ($modules->routeFiles('api.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });
}
