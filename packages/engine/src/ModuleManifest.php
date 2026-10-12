<?php

namespace Tessera\Engine;

/**
 * What a module says about itself: its identity and what it needs in order to work.
 */
final readonly class ModuleManifest
{
    /**
     * @param  list<string>  $requires  Keys of the modules that must be enabled for this one to work.
     * @param  list<string>  $suggests  Keys of modules this one integrates with when they are present.
     * @param  bool  $core  Core modules are always enabled.
     * @param  list<string>  $packages  Composer packages the module needs beyond the framework and the core, so a project
     *                                  that receives the module knows what to `composer require`.
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $version = '1.0.0',
        public array $requires = [],
        public array $suggests = [],
        public bool $core = false,
        public array $packages = [],
    ) {}
}
