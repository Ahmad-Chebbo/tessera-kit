<?php

namespace Tessera\Engine;

use Illuminate\Filesystem\Filesystem;

/**
 * Hands a module over to the project, so its code can be edited. Everything works on the paths it is given: the
 * `module:publish` command passes the real ones, and tests pass a temporary folder.
 */
class ModulePublisher
{
    /**
     * Files of the package that are not part of the module's code.
     *
     * @var list<string>
     */
    private const PACKAGE_FILES = ['composer.json', 'README.md', 'LICENSE', 'LICENSE.md', '.gitattributes', '.gitignore'];

    public function __construct(private readonly Filesystem $files) {}

    /**
     * Copies the whole module into the project and makes the project provide the package itself, so Composer stops
     * installing the vendor copy while everything that requires the package stays satisfied. After this the project
     * owns the module: updates of the package no longer reach it.
     *
     * @return array{package: string, files: int}
     *
     * @throws ModuleException when the module is not a package of its own, or the target exists.
     */
    public function eject(string $source, string $target, string $composerFile, bool $force = false): array
    {
        $package = $this->packageName($source);

        if ($this->files->isDirectory($target) && ! $force) {
            throw new ModuleException("{$target} already exists. Use --force to replace it.");
        }

        $this->files->deleteDirectory($target);
        $copied = 0;

        foreach ($this->files->allFiles($source, true) as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($source))), '/');

            if (in_array($relative, self::PACKAGE_FILES, true) || str_starts_with($relative, '.git/') || str_starts_with($relative, 'node_modules/')) {
                continue;
            }

            $this->files->ensureDirectoryExists(dirname($target.'/'.$relative));
            $this->files->copy($file->getPathname(), $target.'/'.$relative);
            $copied++;
        }

        $this->provideInComposer($composerFile, $package);

        return ['package' => $package, 'files' => $copied];
    }

    /**
     * Copies the pages of the module into the project's own pages, which replace the module's pages of the same name.
     * A page the project already has is kept unless $force is set.
     *
     * @return array{copied: list<string>, skipped: list<string>}
     */
    public function pages(string $source, string $targetPages, bool $force = false): array
    {
        $pages = $source.'/Resources/js/Pages';
        $copied = [];
        $skipped = [];

        if (! $this->files->isDirectory($pages)) {
            return ['copied' => [], 'skipped' => []];
        }

        foreach ($this->files->allFiles($pages, true) as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($pages))), '/');
            $destination = $targetPages.'/'.$relative;

            if ($this->files->exists($destination) && ! $force) {
                $skipped[] = $relative;

                continue;
            }

            $this->files->ensureDirectoryExists(dirname($destination));
            $this->files->copy($file->getPathname(), $destination);
            $copied[] = $relative;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    /**
     * The package a module folder is, which is also what tells a module of its own from a part of a bigger package.
     */
    public function packageName(string $source): string
    {
        $composer = $source.'/composer.json';

        if (! $this->files->exists($composer)) {
            throw new ModuleException('This module is part of a bigger package and cannot be taken out of it. Use --pages to copy only its pages.');
        }

        $name = json_decode($this->files->get($composer), true)['name'] ?? null;

        if (! is_string($name) || $name === '') {
            throw new ModuleException('The composer.json of the module has no package name.');
        }

        return $name;
    }

    private function provideInComposer(string $composerFile, string $package): void
    {
        $composer = json_decode($this->files->get($composerFile), true);

        unset($composer['require'][$package], $composer['require-dev'][$package]);

        foreach (['require', 'require-dev'] as $section) {
            if (($composer[$section] ?? null) === []) {
                unset($composer[$section]);
            }
        }

        $composer['replace'][$package] = '*';
        ksort($composer['replace']);

        $this->files->put($composerFile, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    }
}
