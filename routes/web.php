<?php

use Illuminate\Support\Facades\Route;
use Tessera\Core\Http\HomeController;
use Tessera\Engine\ModuleRegistry;

$modules = app(ModuleRegistry::class);

if ($modules->webEnabled()) {
    Route::get('/', function () {
        return redirect()->route('login');
    });

    Route::middleware('auth')->group(function () use ($modules) {
        Route::get('/home', HomeController::class)->name('home');

        foreach ($modules->routeFiles('web.php') as $moduleRoutes) {
            require $moduleRoutes;
        }
    });

    foreach ($modules->routeFiles('guest.php') as $moduleRoutes) {
        require $moduleRoutes;
    }
}
