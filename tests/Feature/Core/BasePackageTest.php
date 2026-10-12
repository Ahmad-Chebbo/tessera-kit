<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Each package works without the ones above it: the fresh starter installs only the base, and a project for a single
 * business installs the base and the tenant package without Subscription. Their tests may use the layers above; nothing
 * else may.
 */
it('keeps each package free of the layers above it', function (string $package, string $above) {
    $offenders = [];

    foreach (File::allFiles(base_path("packages/{$package}")) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true) || str_contains($path, '/Tests/')) {
            continue;
        }

        if (preg_match("#App\\\\Modules\\\\({$above})\\\\|@modules/({$above})/#", $file->getContents())) {
            $offenders[] = substr($path, strlen(str_replace('\\', '/', base_path())) + 1);
        }
    }

    expect($offenders)->toBe([]);
})->with([
    'base' => ['base', 'Tenant|Subscription'],
    'tenant' => ['tenant', 'Subscription'],
]);

it('builds a fresh starter that requires the engine and the base only', function () {
    $target = sys_get_temp_dir().'/tessera-fresh-'.uniqid();

    try {
        $build = Process::path(base_path())->run([PHP_BINARY, 'bin/build-starter.php', $target, '--flavor=fresh', '--source=path']);
        expect($build->successful())->toBeTrue($build->errorOutput().$build->output());

        $composer = json_decode(file_get_contents($target.'/composer.json'), true);
        $required = array_keys($composer['require']);

        expect($composer['name'])->toBe('tessera/tessera-fresh')
            ->and($required)->toContain('tessera/tessera-engine', 'tessera/tessera-base')
            ->and($required)->not->toContain('tessera/tessera-tenant', 'tessera/tessera-saas')
            ->and(collect($required)->filter(fn (string $name): bool => str_starts_with($name, 'tessera/tessera-'))->count())->toBe(2)
            ->and($composer['autoload'])->not->toHaveKey('files')
            ->and(file_exists($target.'/app/Helpers/currency.php'))->toBeFalse()
            ->and(file_get_contents($target.'/README.md'))->toContain('Tessera Fresh');
    } finally {
        File::deleteDirectory($target);
    }
});

it('builds the SaaS starter with the core on top of the base', function () {
    $target = sys_get_temp_dir().'/tessera-saas-'.uniqid();

    try {
        $build = Process::path(base_path())->run([PHP_BINARY, 'bin/build-starter.php', $target, '--source=path']);
        expect($build->successful())->toBeTrue($build->errorOutput().$build->output());

        $composer = json_decode(file_get_contents($target.'/composer.json'), true);

        expect($composer['name'])->toBe('tessera/tessera-starter')
            ->and(array_keys($composer['require']))->toContain('tessera/tessera-engine', 'tessera/tessera-base', 'tessera/tessera-tenant', 'tessera/tessera-saas')
            ->and(file_exists($target.'/app/Helpers/currency.php'))->toBeTrue();
    } finally {
        File::deleteDirectory($target);
    }
});
