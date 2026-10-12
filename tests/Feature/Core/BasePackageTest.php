<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * The base package (Auth and Team) has to work without the core (Company and Subscription), because the fresh starter
 * installs only the base. Its tests may use the core; nothing else may.
 */
it('keeps the base package free of the Company and Subscription modules', function () {
    $offenders = [];

    foreach (File::allFiles(base_path('packages/base')) as $file) {
        $path = str_replace('\\', '/', $file->getPathname());

        if (! in_array($file->getExtension(), ['php', 'vue', 'js'], true) || str_contains($path, '/Tests/')) {
            continue;
        }

        if (preg_match('#App\\\\Modules\\\\(Company|Subscription)\\\\|@modules/(Company|Subscription)/#', $file->getContents())) {
            $offenders[] = substr($path, strlen(str_replace('\\', '/', base_path())) + 1);
        }
    }

    expect($offenders)->toBe([]);
});

it('builds a fresh starter that requires the engine and the base only', function () {
    $target = sys_get_temp_dir().'/tessera-fresh-'.uniqid();

    try {
        $build = Process::path(base_path())->run([PHP_BINARY, 'bin/build-starter.php', $target, '--flavor=fresh', '--source=path']);
        expect($build->successful())->toBeTrue($build->errorOutput().$build->output());

        $composer = json_decode(file_get_contents($target.'/composer.json'), true);
        $required = array_keys($composer['require']);

        expect($composer['name'])->toBe('ahmad-chebbo/tessera-fresh')
            ->and($required)->toContain('ahmad-chebbo/tessera-engine', 'ahmad-chebbo/tessera-base')
            ->and($required)->not->toContain('ahmad-chebbo/tessera-saas')
            ->and(collect($required)->filter(fn (string $name): bool => str_starts_with($name, 'ahmad-chebbo/tessera-'))->count())->toBe(2)
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

        expect($composer['name'])->toBe('ahmad-chebbo/tessera-starter')
            ->and(array_keys($composer['require']))->toContain('ahmad-chebbo/tessera-engine', 'ahmad-chebbo/tessera-base', 'ahmad-chebbo/tessera-saas')
            ->and(file_exists($target.'/app/Helpers/currency.php'))->toBeTrue();
    } finally {
        File::deleteDirectory($target);
    }
});
