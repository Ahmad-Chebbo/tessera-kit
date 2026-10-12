<?php

namespace Tessera\Engine;

use Illuminate\Support\ServiceProvider;
use Tessera\Engine\Console\ModuleAddCommand;
use Tessera\Engine\Console\ModuleCheckCommand;
use Tessera\Engine\Console\ModuleListCommand;
use Tessera\Engine\Console\ModuleMakeCommand;
use Tessera\Engine\Console\ModulePackageCommand;
use Tessera\Engine\Console\ModulePublishCommand;

/**
 * Finds the module providers in app/Modules, builds the registry and registers the enabled modules.
 */
class EngineServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/modules.php', 'modules');
        $this->mergeConfigFrom(__DIR__.'/../config/tessera.php', 'tessera');

        $registry = new ModuleRegistry(
            $this->discover(),
            config('modules.enabled', '*'),
            config('modules.layers', ['web', 'api']),
        );

        $this->app->instance(ModuleRegistry::class, $registry);

        foreach ($registry->enabled() as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/modules.php' => config_path('modules.php'),
                __DIR__.'/../config/tessera.php' => config_path('tessera.php'),
            ], ['tessera-config', 'tessera-engine-config']);

            $this->commands([ModuleAddCommand::class, ModuleListCommand::class, ModuleMakeCommand::class, ModuleCheckCommand::class, ModulePackageCommand::class, ModulePublishCommand::class]);
        }

        $this->registerPagePaths();
    }

    /**
     * @return list<ModuleServiceProvider>
     */
    private function discover(): array
    {
        $classes = [];

        foreach (glob(app_path('Modules/*/*ServiceProvider.php')) ?: [] as $file) {
            $classes[] = 'App\\Modules\\'.basename(dirname($file)).'\\'.basename($file, '.php');
        }

        foreach ((new ComposerModules)->classes(base_path('vendor/composer/installed.json'), $this->app->bootstrapPath('cache/tessera-modules.php')) as $class) {
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        foreach (config('modules.extra', []) as $class) {
            $classes[] = $class;
        }

        return array_map(fn (string $class): ModuleServiceProvider => new $class($this->app), array_values(array_unique($classes)));
    }

    /**
     * Tells Inertia where the pages live, so tests can check that a rendered component exists.
     */
    private function registerPagePaths(): void
    {
        $registry = $this->app->make(ModuleRegistry::class);

        if (! $registry->webEnabled()) {
            return;
        }

        $paths = [resource_path('js/Pages')];

        foreach ($registry->enabled() as $provider) {
            if (is_dir($pages = $provider->modulePath('Resources/js/Pages'))) {
                $paths[] = $pages;
            }
        }

        config(['inertia.pages.paths' => $paths]);
    }
}
