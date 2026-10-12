<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Tessera\Engine\ModuleRegistry;

class ModulePackageCommand extends Command
{
    protected $signature = 'module:package {key : The key of the module, for example expenses}
        {--vendor=ahmad-chebbo : The Composer vendor name}
        {--prefix=tessera- : Put before the module key to make the package name}
        {--path=packages : The folder, relative to the project, to write the package to}
        {--force : Replace a package folder that already exists}';

    protected $description = 'Turn a module into a Composer package that other projects can require';

    public function handle(ModuleRegistry $registry, Filesystem $files): int
    {
        $key = (string) $this->argument('key');

        if (! isset($registry->all()[$key])) {
            $this->components->error("No module with the key [{$key}].");

            return self::FAILURE;
        }

        $provider = $registry->all()[$key];
        $manifest = $provider->manifest();

        if ($manifest->core) {
            $this->components->error('Core modules stay in the application and cannot be packaged.');

            return self::FAILURE;
        }

        $name = explode('\\', $provider::class)[2];
        $target = base_path(trim((string) $this->option('path'), '/\\')."/{$key}");

        if ($files->isDirectory($target) && ! $this->option('force')) {
            $this->components->error("{$target} already exists. Use --force to replace it.");

            return self::FAILURE;
        }

        $files->deleteDirectory($target);
        $files->copyDirectory($provider->modulePath(), $target);

        $vendor = (string) $this->option('vendor');
        $prefix = (string) $this->option('prefix');
        $requires = ['php' => '^8.4', "{$vendor}/{$prefix}engine" => '^1.0', "{$vendor}/{$prefix}core" => '^1.0'];

        foreach ($manifest->packages as $package) {
            $requires[$package] = '*';
        }

        foreach ($manifest->requires as $required) {
            if (! ($registry->all()[$required]->manifest()->core ?? true)) {
                $requires["{$vendor}/{$prefix}{$required}"] = '^1.0';
            }
        }

        $files->put("{$target}/composer.json", json_encode([
            'name' => "{$vendor}/{$prefix}{$key}",
            'description' => "The {$manifest->name} module.",
            'type' => 'library',
            'version' => $manifest->version,
            'require' => $requires,
            'autoload' => ['psr-4' => ["App\\Modules\\{$name}\\" => '']],
            'extra' => ['tessera' => ['modules' => [$provider::class]]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

        $relative = trim((string) $this->option('path'), '/\\')."/{$key}";

        $this->components->info("Packaged {$key} into {$relative}.");
        $this->line('To use it in a project, add to that project\'s composer.json:');
        $this->line('  "repositories": [{ "type": "path", "url": "'.$relative.'", "options": { "symlink": true } }]');
        $this->line("then run <info>composer require {$vendor}/{$prefix}{$key}:@dev</info>, remove app/Modules/{$name} if it is there, and run <info>php artisan migrate</info>, <info>php artisan modules:sync</info> and <info>bun run build</info>.");

        return self::SUCCESS;
    }
}
