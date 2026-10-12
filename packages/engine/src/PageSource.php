<?php

namespace Tessera\Engine;

/**
 * Finds the source file of an Inertia page. Pages live in resources/js/Pages or in a module's Resources/js/Pages, so the
 * layout cannot assume one folder when it asks Vite to preload the page.
 */
class PageSource
{
    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return string|null The path relative to the project root, with forward slashes, or null when no page has that name.
     */
    public function pathFor(string $component): ?string
    {
        $folders = [resource_path('js/Pages')];

        foreach ($this->registry->enabled() as $provider) {
            $folders[] = $provider->modulePath('Resources/js/Pages');
        }

        foreach ($folders as $folder) {
            $file = "{$folder}/{$component}.vue";

            if (is_file($file)) {
                $root = str_replace('\\', '/', base_path()).'/';
                $path = str_replace('\\', '/', $file);

                return str_starts_with($path, $root) && $this->isKnownToVite($relative = substr($path, strlen($root))) ? $relative : null;
            }
        }

        return null;
    }

    /**
     * Vite can only preload a page it knows: any page while the dev server runs, and the built ones otherwise. A page
     * added after the last build is simply not preloaded.
     */
    private function isKnownToVite(string $path): bool
    {
        if (is_file(public_path('hot'))) {
            return true;
        }

        static $manifest = null;

        $manifest ??= is_file($file = public_path('build/manifest.json')) ? json_decode((string) file_get_contents($file), true) : [];

        return isset($manifest[$path]);
    }
}
