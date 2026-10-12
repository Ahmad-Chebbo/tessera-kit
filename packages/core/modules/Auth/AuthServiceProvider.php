<?php

namespace App\Modules\Auth;

use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleServiceProvider;

/**
 * Authentication module.
 */
class AuthServiceProvider extends ModuleServiceProvider
{
    public function manifest(): ModuleManifest
    {
        return new ModuleManifest(
            key: 'auth',
            name: 'Authentication',
            requires: ['team'],
            core: true,
        );
    }
}
