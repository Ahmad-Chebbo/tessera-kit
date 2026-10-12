<?php

use Tessera\Engine\ModuleRegistry;

/**
 * Helpers for tests that read the frontend source. A file may live in resources/js or in a module's
 * Resources/js folder; callers name it by its resources/js path and these helpers find it.
 */
const NOT_INSTALLED = '(not installed)';

function projectRoot(): string
{
    return dirname(__DIR__, 2);
}

/**
 * Every module folder in the project, found without booting the application: the host's own app/Modules, the monorepo
 * packages and modules installed with Composer. The same folder reached through a symlink is listed once.
 *
 * @return array<string, string> Absolute folder keyed by the module's name in its namespace, for example Expenses.
 */
function moduleFolders(): array
{
    static $folders = null;

    if ($folders !== null) {
        return $folders;
    }

    $folders = [];
    $patterns = [
        'app/Modules/*', 'packages/modules/*', 'packages/base/modules/*', 'packages/tenant/modules/*', 'packages/saas/modules/*',
        'vendor/tessera/*', 'vendor/tessera/*/modules/*',
    ];

    foreach ($patterns as $pattern) {
        foreach (glob(projectRoot().'/'.$pattern, GLOB_ONLYDIR) ?: [] as $folder) {
            $provider = glob($folder.'/*ServiceProvider.php')[0] ?? null;
            $real = realpath($folder);

            if ($provider !== null && ! in_array($real, $folders, true)) {
                $folders[basename($provider, 'ServiceProvider.php')] = $real;
            }
        }
    }

    return $folders;
}

/**
 * The source folder of the tessera-ui package: the workspace copy in the monorepo, otherwise the installed package.
 */
function uiSource(): ?string
{
    foreach (['/packages/ui/src', '/node_modules/@tessera/tessera-ui/src'] as $path) {
        if (is_dir(projectRoot().$path)) {
            return realpath(projectRoot().$path);
        }
    }

    return null;
}

/**
 * @return list<string> Every folder that holds frontend source: the core one and each module's.
 */
function frontendRoots(): array
{
    $roots = [projectRoot().'/resources/js'];

    if ($ui = uiSource()) {
        $roots[] = $ui;
    }

    foreach (moduleFolders() as $folder) {
        if (is_dir($folder.'/Resources/js')) {
            $roots[] = $folder.'/Resources/js';
        }
    }

    return $roots;
}

/**
 * Absolute path of a project file; a resources/js path that is not there is looked up in the modules.
 */
function frontendPath(string $relative): string
{
    $direct = projectRoot().'/'.$relative;

    if (file_exists($direct) || ! str_starts_with($relative, 'resources/js/')) {
        return $direct;
    }

    $inner = substr($relative, strlen('resources/js/'));

    if (($ui = uiSource()) && file_exists($candidate = $ui.'/'.$inner)) {
        return $candidate;
    }

    foreach (moduleFolders() as $folder) {
        if (file_exists($candidate = $folder.'/Resources/js/'.$inner)) {
            return $candidate;
        }
    }

    return $direct;
}

/**
 * @return list<SplFileInfo> Every file of the frontend source, optionally only below a sub folder such as "Pages".
 */
function frontendFiles(?string $subFolder = null): array
{
    $files = [];

    foreach (frontendRoots() as $root) {
        $folder = $subFolder === null ? $root : $root.'/'.$subFolder;

        if (! is_dir($folder)) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            $files[] = $file;
        }
    }

    return $files;
}

/**
 * Whether a module's folder is in this project. Unlike moduleInstalled() it needs no booted application, so it can
 * decide at load time whether a whole describe() block runs.
 */
function moduleFolderInstalled(string $folder): bool
{
    return isset(moduleFolders()[$folder]);
}

/**
 * Whether the project has this module enabled, for tests that only make sense with it.
 */
function moduleInstalled(string $key): bool
{
    return app(ModuleRegistry::class)->has($key);
}

/**
 * Keeps the dataset rows whose file exists; the first value of a row is the file, either a path under resources/js or,
 * with a prefix such as "Pages/", relative to it. Rows for modules that are not installed drop out.
 *
 * @param  array<int|string, mixed>  $rows
 * @return array<int|string, mixed>
 */
function installedFiles(array $rows, string $prefix = ''): array
{
    $installed = array_values(array_filter($rows, function (mixed $row) use ($prefix): bool {
        $path = is_array($row) ? $row[0] : $row;

        return is_file(frontendPath(str_starts_with($path, 'resources/') ? $path : 'resources/js/'.$prefix.$path));
    }));

    if ($installed !== [] || $rows === []) {
        return $installed;
    }

    $first = $rows[0];

    return [is_array($first) ? [NOT_INSTALLED, ...array_slice($first, 1)] : NOT_INSTALLED];
}

/**
 * Skips a dataset test whose row stands for modules that are not installed.
 */
function requireInstalled(string $file): void
{
    if ($file === NOT_INSTALLED) {
        test()->markTestSkipped('The module that owns these files is not installed.');
    }
}

/**
 * Translation messages as the app sees them: the core file plus the module files, merged.
 *
 * @return array<string, mixed>
 */
function frontendMessages(string $locale): array
{
    $messages = json_decode(file_get_contents(projectRoot()."/resources/js/i18n/locales/{$locale}.json"), true);

    if ($ui = uiSource()) {
        $messages = array_replace_recursive(json_decode(file_get_contents("{$ui}/locales/{$locale}.json"), true), $messages);
    }

    foreach (moduleFolders() as $folder) {
        if (is_file($file = "{$folder}/Resources/lang/{$locale}.json")) {
            $messages = array_replace_recursive($messages, json_decode(file_get_contents($file), true));
        }
    }

    return $messages;
}
