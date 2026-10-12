<?php

/**
 * Builds the tessera-starter project from this monorepo: the host application without the packages folder, requiring the
 * engine and the core, to which modules are added with `php artisan module:add`.
 *
 *     php bin/build-starter.php <target-folder> [--source=vcs|path] [--vendor=ahmad-chebbo]
 *
 * --source=vcs   requires the packages from their GitHub repositories (what a release does).
 * --source=path  requires them from the packages folder of this monorepo, copied, so the build can be tried offline.
 */
$arguments = array_slice($argv, 1);
$target = null;
$options = ['source' => 'vcs', 'vendor' => 'ahmad-chebbo'];

foreach ($arguments as $argument) {
    if (preg_match('/^--(\w+)=(.+)$/', $argument, $match)) {
        $options[$match[1]] = $match[2];
    } else {
        $target = $argument;
    }
}

if ($target === null) {
    fwrite(STDERR, "Usage: php bin/build-starter.php <target-folder> [--source=vcs|path] [--vendor=name]\n");
    exit(1);
}

$root = dirname(__DIR__);
$vendor = $options['vendor'];
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

$corePackages = array_keys(json_decode(file_get_contents($root.'/packages/core/composer.json'), true)['require']);

foreach (array_keys($composer['require']) as $package) {
    $isFeatureModule = str_starts_with($package, "{$vendor}/tessera-") && ! in_array($package, ["{$vendor}/tessera-engine", "{$vendor}/tessera-core"], true);
    $isModuleOnlyPackage = isset($modulePackages[$package]) && ! in_array($package, $corePackages, true) && ! str_starts_with($package, "{$vendor}/");

    if ($isFeatureModule || $isModuleOnlyPackage) {
        unset($composer['require'][$package]);
    }
}

$constraint = $options['source'] === 'path' ? '@dev' : '^1.0';
$composer['name'] = "{$vendor}/tessera-starter";
$composer['description'] = 'A Laravel application ready for Tessera modules: add one with php artisan module:add.';
$composer['require']["{$vendor}/tessera-engine"] = $constraint;
$composer['require']["{$vendor}/tessera-core"] = $constraint;
ksort($composer['require']);

if ($options['source'] === 'path') {
    $composer['repositories'] = [
        ['type' => 'path', 'url' => $root.'/packages/engine', 'options' => ['symlink' => false]],
        ['type' => 'path', 'url' => $root.'/packages/core', 'options' => ['symlink' => false]],
    ];
} else {
    $composer['repositories'] = [
        ['type' => 'vcs', 'url' => "https://github.com/{$vendor}/tessera-engine"],
        ['type' => 'vcs', 'url' => "https://github.com/{$vendor}/tessera-core"],
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
$package['name'] = 'tessera-starter';
$package['dependencies']['@ahmad-chebbo/tessera-ui'] = $options['source'] === 'path' ? 'file:'.$root.'/packages/ui' : "github:{$vendor}/tessera-ui";
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

echo "Built the starter in {$target}\n";
