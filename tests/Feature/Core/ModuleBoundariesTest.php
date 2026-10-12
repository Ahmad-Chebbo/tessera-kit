<?php

use Tessera\Engine\ModuleChecker;
use Tessera\Engine\ModuleRegistry;

/**
 * Cross-module imports that are not allowed yet. A module may use App\Core, the core modules and the modules it
 * requires; everything else must go through a contract. The list only shrinks: fixing an edge makes the test ask for
 * its removal, and a new edge fails the test.
 */
const KNOWN_BOUNDARY_VIOLATIONS = [
];

it('keeps modules to core and their own requirements, apart from the known edges', function () {
    expect(array_keys(app(ModuleChecker::class)->boundaryViolations()))->toBe(KNOWN_BOUNDARY_VIOLATIONS);
});

it('keeps domain code free of Inertia and HTTP requests', function () {
    $offenders = [];

    foreach (app(ModuleRegistry::class)->all() as $provider) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($provider->modulePath(), FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if ($file->getExtension() !== 'php' || preg_match('#/(Http|Routes|Resources|Tests|Database)/|ServiceProvider\.php$#', $path)) {
                continue;
            }

            if (preg_match('/use (Inertia\\\\|Illuminate\\\\Http\\\\Request;)/', file_get_contents($path))) {
                $offenders[] = basename(dirname($path)).'/'.basename($path);
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('keeps transactions and row locks out of controllers', function () {
    $offenders = [];

    foreach (moduleFolders() as $name => $folder) {
        foreach (glob($folder.'/Http/Controllers/*/*.php') ?: [] as $controller) {
            if (preg_match('/DB::transaction|lockForUpdate/', file_get_contents($controller))) {
                $offenders[] = $name.'/'.basename($controller);
            }
        }
    }

    expect($offenders)->toBe([]);
});
