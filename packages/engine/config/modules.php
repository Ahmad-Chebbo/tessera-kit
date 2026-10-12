<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled modules
    |--------------------------------------------------------------------------
    |
    | "*" enables every module found in app/Modules. Otherwise a comma separated list of module keys
    | (MODULES_ENABLED=sales,catalog) or "none" for the core modules only. Core modules are always enabled, and a module whose requirements
    | are not enabled makes the application refuse to boot.
    |
    */

    'enabled' => env('MODULES_ENABLED', '*'),

    /*
    |--------------------------------------------------------------------------
    | Layers
    |--------------------------------------------------------------------------
    |
    | "web" serves the Inertia/Vue pages, "api" serves the JSON API. The domain layer (models, services,
    | migrations) is always loaded. A project without Inertia installed runs with MODULES_LAYERS=api.
    |
    */

    'layers' => array_filter(array_map('trim', explode(',', (string) env('MODULES_LAYERS', 'web,api')))),

    /*
    |--------------------------------------------------------------------------
    | Extra module providers
    |--------------------------------------------------------------------------
    |
    | Module service providers that do not live in app/Modules, for example from a Composer package.
    |
    */

    'extra' => [],

];
