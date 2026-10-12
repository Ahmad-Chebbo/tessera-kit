<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Where modules are published
    |--------------------------------------------------------------------------
    |
    | `module:add expenses` requires "{vendor}/{package_prefix}expenses" from the repository below.
    |
    */

    'vendor' => 'ahmad-chebbo',

    'package_prefix' => 'tessera-',

    'repository' => 'https://github.com/{vendor}/{package}',

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
        base_path('node_modules/@ahmad-chebbo/tessera-ui/src/locales/en.json'),
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
