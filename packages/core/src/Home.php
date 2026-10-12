<?php

namespace Tessera\Core;

use Illuminate\Support\Facades\Route;

/**
 * The page to send people to after they sign in: the dashboard when the project has one, otherwise the home route
 * that picks the first page their menu offers.
 */
final class Home
{
    public static function url(): string
    {
        return route(Route::has('dashboard') ? 'dashboard' : 'home', absolute: false);
    }
}
