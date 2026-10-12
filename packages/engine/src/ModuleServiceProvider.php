<?php

namespace Tessera\Engine;

use App\Modules\Team\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Tessera\Engine\Dashboard\DashboardWidget;

/**
 * Base class of every module's provider. A module describes itself through the hooks below and the application
 * discovers and wires it, so nothing outside the module's folder needs editing to add or remove it.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    abstract public function manifest(): ModuleManifest;

    /**
     * @return list<PlanFeature>
     */
    public function features(): array
    {
        return [];
    }

    /**
     * Role editor groups: group name => permissions.
     *
     * @return array<string, list<string>>
     */
    public function permissionGroups(): array
    {
        return [];
    }

    /**
     * Permissions the default roles receive: role name => permissions.
     *
     * @return array<string, list<string>>
     */
    public function defaultRolePermissions(): array
    {
        return [];
    }

    /**
     * Permissions that belong to no plan feature and no role editor group, such as platform administration.
     *
     * @return list<string>
     */
    public function extraPermissions(): array
    {
        return [];
    }

    /**
     * Every permission this module declares, wherever it is declared.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $names = $this->extraPermissions();

        foreach ($this->features() as $feature) {
            $names = [...$names, ...$feature->permissions];
        }

        foreach ($this->permissionGroups() as $permissions) {
            $names = [...$names, ...$permissions];
        }

        foreach ($this->defaultRolePermissions() as $permissions) {
            $names = [...$names, ...$permissions];
        }

        return array_values(array_unique($names));
    }

    /**
     * @return list<MenuItem>
     */
    public function menu(): array
    {
        return [];
    }

    public function schedule(Schedule $schedule): void {}

    /**
     * Export report definitions this module offers.
     *
     * @return list<class-string>
     */
    public function exports(): array
    {
        return [];
    }

    /**
     * Seeders `php artisan db:seed` runs for this module, after the seeders of the modules it requires.
     *
     * @return list<class-string<Seeder>>
     */
    public function seeders(): array
    {
        return [];
    }

    /**
     * Blocks this module adds to the dashboard.
     *
     * @return list<class-string<DashboardWidget>>
     */
    public function dashboardWidgets(): array
    {
        return [];
    }

    /**
     * Inertia shared props, only used by the web layer. Each closure receives the request and the signed-in user.
     *
     * @return array<string, \Closure(Request, ?User): mixed>
     */
    public function sharedProps(): array
    {
        return [];
    }

    public function register(): void
    {
        if (is_file($config = $this->modulePath('config.php'))) {
            $this->mergeConfigFrom($config, $this->manifest()->key);
        }

        $this->bindings();
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole() && is_file($config = $this->modulePath('config.php'))) {
            $key = $this->manifest()->key;

            $this->publishes([$config => config_path($key.'.php')], ['tessera-config', 'tessera-modules-config', "tessera-{$key}-config"]);
        }

        if (is_dir($migrations = $this->modulePath('Database/Migrations'))) {
            $this->loadMigrationsFrom($migrations);
        }

        if (is_dir($lang = $this->modulePath('Resources/lang'))) {
            $this->loadTranslationsFrom($lang, $this->manifest()->key);
        }

        $this->callAfterResolving(Schedule::class, fn (Schedule $schedule) => $this->schedule($schedule));
    }

    protected function bindings(): void {}

    /**
     * The module's folder, or a path inside it.
     */
    public function modulePath(string $path = ''): string
    {
        $base = dirname((new ReflectionClass(static::class))->getFileName());

        return $path === '' ? $base : $base.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
}
