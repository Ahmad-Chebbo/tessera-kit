<?php

/**
 * Builds a starter project from this monorepo: the host application without the packages folder, to which modules are
 * added with `php artisan module:add`.
 *
 *     php bin/build-starter.php <target-folder> [--flavor=saas|fresh] [--source=vcs|path] [--vendor=ahmad-chebbo]
 *
 * --flavor=saas   (default) tessera-starter: the engine, the base and the core, so companies, branches and plans.
 * --flavor=fresh  tessera-fresh: the engine and the base only, so sign-in, users, roles and permissions.
 * --source=vcs    requires the packages from their GitHub repositories (what a release does).
 * --source=path   requires them from the packages folder of this monorepo, copied, so the build can be tried offline.
 */
$arguments = array_slice($argv, 1);
$target = null;
$options = ['source' => 'vcs', 'vendor' => 'ahmad-chebbo', 'flavor' => 'saas'];

foreach ($arguments as $argument) {
    if (preg_match('/^--(\w+)=(.+)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    } else {
        $target = $argument;
    }
}

if ($target === null) {
    fwrite(STDERR, "Usage: php bin/build-starter.php <target-folder> [--flavor=saas|fresh] [--source=vcs|path] [--vendor=name]\n");
    exit(1);
}

if (! in_array($options['flavor'], ['saas', 'fresh'], true)) {
    fwrite(STDERR, "The flavor must be saas or fresh.\n");
    exit(1);
}

$fresh = $options['flavor'] === 'fresh';

$root = dirname(__DIR__);
$vendor = $options['vendor'];
$composerVendor = strtolower($vendor);
$excluded = [
    '.git', '.claude', '.cursor', '.agents', '.superpowers', '.phpunit.cache', 'vendor', 'node_modules', 'packages', 'docs', 'bin',
    'public/build', 'public/hot', 'storage/logs', 'storage/framework', 'tests/Product', 'AGENTS.md', 'CLAUDE.md', 'boost.json',
    '.mcp.json', '.env', 'composer.lock', 'bun.lock',
];

if (is_dir($target) && count(scandir($target)) > 2) {
    fwrite(STDERR, "The folder {$target} is not empty.\n");
    exit(1);
}

function copyTree(string $from, string $to, array $excluded, string $base): void
{
    @mkdir($to, 0777, true);

    foreach (scandir($from) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }

        $relative = ltrim(substr($from.'/'.$entry, strlen($base)), '/');

        if (in_array($relative, $excluded, true)) {
            continue;
        }

        if (is_dir($from.'/'.$entry)) {
            copyTree($from.'/'.$entry, $to.'/'.$entry, $excluded, $base);
        } else {
            copy($from.'/'.$entry, $to.'/'.$entry);
        }
    }
}

function writeJson(string $file, array $data): void
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
}

copyTree($root, $target, $excluded, $root);

foreach (['bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $folder) {
    @mkdir($target.'/'.$folder, 0777, true);
    file_put_contents($target.'/'.$folder.'/.gitignore', "*\n!.gitignore\n");
}

// ---------------------------------------------------------------- composer.json
$composer = json_decode(file_get_contents($root.'/composer.json'), true);
$modulePackages = [];

foreach (glob($root.'/packages/modules/*/composer.json') as $file) {
    foreach (array_keys(json_decode(file_get_contents($file), true)['require'] ?? []) as $package) {
        $modulePackages[$package] = true;
    }
}

$kept = $fresh ? ['engine', 'base'] : ['engine', 'base', 'core'];
$keptPackages = array_map(fn (string $name): string => "{$composerVendor}/tessera-{$name}", $kept);
$corePackages = [];

foreach ($kept as $name) {
    $corePackages = [...$corePackages, ...array_keys(json_decode(file_get_contents($root."/packages/{$name}/composer.json"), true)['require'])];
}

foreach (array_keys($composer['require']) as $package) {
    $isFeatureModule = str_starts_with($package, "{$composerVendor}/tessera-") && ! in_array($package, $keptPackages, true);
    $isModuleOnlyPackage = isset($modulePackages[$package]) && ! in_array($package, $corePackages, true) && ! str_starts_with($package, "{$composerVendor}/");

    if ($isFeatureModule || $isModuleOnlyPackage) {
        unset($composer['require'][$package]);
    }
}

$constraint = $options['source'] === 'path' ? '@dev' : '^1.0';
$composer['name'] = $fresh ? "{$composerVendor}/tessera-fresh" : "{$composerVendor}/tessera-starter";
$composer['description'] = $fresh
    ? 'A Laravel application with sign-in, users, roles and permissions, ready for Tessera modules.'
    : 'A Laravel application ready for Tessera modules: add one with php artisan module:add.';

foreach ($keptPackages as $package) {
    $composer['require'][$package] = $constraint;
}

ksort($composer['require']);

if ($fresh) {
    unset($composer['autoload']['files']);
}

if ($options['source'] === 'path') {
    $composer['repositories'] = array_map(
        fn (string $name): array => ['type' => 'path', 'url' => $root."/packages/{$name}", 'options' => ['symlink' => false]],
        $kept,
    );
} else {
    $composer['repositories'] = [
        ['type' => 'composer', 'url' => 'https://'.strtolower($vendor).'.github.io/tessera-kit'],
    ];
}

foreach ($composer['scripts'] ?? [] as $event => $commands) {
    if (is_array($commands)) {
        $composer['scripts'][$event] = array_values(array_filter($commands, fn (string $command): bool => ! str_contains($command, 'boost:')));
    }
}

writeJson($target.'/composer.json', $composer);

// ---------------------------------------------------------------- package.json
$package = json_decode(file_get_contents($root.'/package.json'), true);
unset($package['workspaces']);
$package['name'] = $fresh ? 'tessera-fresh' : 'tessera-starter';
$package['dependencies']['@ahmad-chebbo/tessera-ui'] = $options['source'] === 'path' ? 'file:'.$root.'/packages/ui' : "github:{$vendor}/tessera-ui".(isset($options['uiref']) ? '#'.$options['uiref'] : '');
writeJson($target.'/package.json', $package);

// ---------------------------------------------------------------- tests of the monorepo that need its packages folder
$pest = file_get_contents($target.'/tests/Pest.php');
$pest = preg_replace("/->in\\('Feature', 'Product'[^;]*\\);/", "->in('Feature', '../app/Modules/*/Tests/Feature');", $pest);
file_put_contents($target.'/tests/Pest.php', $pest);

$phpunit = file_get_contents($target.'/phpunit.xml');
$phpunit = preg_replace('#\s*<directory>(tests/Product|packages/[^<]*)</directory>#', '', $phpunit);
file_put_contents($target.'/phpunit.xml', $phpunit);

$vitest = file_get_contents($target.'/vitest.config.js');
$vitest = preg_replace("#\s*'packages/ui/src/\*\*/\*\.test\.js',#", '', $vitest);
file_put_contents($target.'/vitest.config.js', $vitest);

$viteConfig = $target.'/.gitignore';
file_put_contents($viteConfig, rtrim((string) file_get_contents($viteConfig))."\n/bootstrap/cache/*.php\n");

if ($fresh) {
    // Currency formatting belongs to the Company module.
    @unlink($target.'/app/Helpers/currency.php');
    @rmdir($target.'/app/Helpers');
}

copy(__DIR__.($fresh ? '/fresh-README.md' : '/starter-README.md'), $target.'/README.md');

echo "Built the {$options['flavor']} starter in {$target}\n";
