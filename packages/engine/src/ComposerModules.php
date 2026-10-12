<?php

namespace Tessera\Engine;

/**
 * Finds the module providers that Composer packages advertise. A package lists them in its composer.json:
 *
 *     "extra": { "tessera": { "modules": ["App\\Modules\\Invoices\\InvoicesServiceProvider"] } }
 *
 * Reading Composer's installed.json on every request would be wasteful, so the answer is kept in a cache file that is
 * renewed when installed.json changes.
 */
class ComposerModules
{
    /**
     * @return list<string> Provider classes.
     */
    public function classes(string $installedJson, ?string $cacheFile = null): array
    {
        if (! is_file($installedJson)) {
            return [];
        }

        $stamp = (string) filemtime($installedJson);

        if ($cacheFile !== null && is_file($cacheFile)) {
            $cached = @include $cacheFile;

            if (is_array($cached) && ($cached['stamp'] ?? null) === $stamp) {
                return $cached['classes'];
            }
        }

        $data = json_decode((string) file_get_contents($installedJson), true);
        $classes = [];

        foreach ($data['packages'] ?? $data ?? [] as $package) {
            foreach ($package['extra']['tessera']['modules'] ?? [] as $class) {
                $classes[] = $class;
            }
        }

        $classes = array_values(array_unique($classes));

        if ($cacheFile !== null) {
            @file_put_contents($cacheFile, '<?php return '.var_export(['stamp' => $stamp, 'classes' => $classes], true).';');
        }

        return $classes;
    }
}
