<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where modules are published
    |--------------------------------------------------------------------------
    |
    | `module:add expenses` requires "{vendor}/{package_prefix}expenses" from the Composer repository below, which lists
    | every package at once. It is added to composer.json when the project does not have it yet.
    |
    */

    'vendor' => 'tessera',

    'package_prefix' => 'tessera-',

    'repository' => 'https://tessera-kit.github.io/tessera-kit',

    /*
    |--------------------------------------------------------------------------
    | Modules that come from the tenant and SaaS packages
    |--------------------------------------------------------------------------
    |
    | `module:package` requires the base package for every module, the tenant package too when the module requires one of
    | the first list (the tenant, branches, stock locations), and the SaaS package when it requires one of the second
    | (plans, subscriptions).
    |
    */

    'tenant_modules' => ['tenant'],

    'saas_modules' => ['subscription'],

    /*
    |--------------------------------------------------------------------------
    | Core translation files
    |--------------------------------------------------------------------------
    |
    | English files that hold the texts every module may use without declaring them: the application's own and the
    | interface kit's. `module:check` reads them to tell a module's missing translations from shared ones.
    |
    */

    'translation_files' => [
        resource_path('js/i18n/locales/en.json'),
        base_path('node_modules/@tessera/tessera-ui/src/locales/en.json'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contracts
    |--------------------------------------------------------------------------
    |
    | Interfaces that modules share. Packages add theirs; `module:check` makes sure each can be resolved.
    |
    */

    'contracts' => [],

];
