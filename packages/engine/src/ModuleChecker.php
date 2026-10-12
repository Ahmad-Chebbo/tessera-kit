<?php

namespace Tessera\Engine;

use Composer\InstalledVersions;
use FilesystemIterator;
use Illuminate\Support\Arr;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tessera\Engine\Dashboard\DashboardWidget;
use Tessera\Engine\Exports\ReportDefinition;
use Throwable;

/**
 * Looks for the mistakes that stop a module from being dropped into another project: a manifest that says one thing
 * while the code does another, imports of modules it does not require, permissions that nobody declares, translations
 * that exist in one language only, and so on.
 */
class ModuleChecker
{
    public const ERROR = 'error';

    public const WARNING = 'warning';

    /**
     * @var list<array{level: string, module: string, message: string}>
     */
    private array $issues = [];

    public function __construct(private readonly ModuleRegistry $registry) {}

    /**
     * @return list<array{level: string, module: string, message: string}>
     */
    public function run(): array
    {
        $this->issues = [];

        $this->manifests();

        foreach ($this->boundaryViolations() as $edge => $files) {
            [$module, $other] = explode(' -> ', $edge);
            $this->add(self::ERROR, $module, "uses the {$other} module (".implode(', ', array_slice($files, 0, 3)).') without requiring it; require it or go through a contract');
        }

        $this->domainPurity();
        $this->controllers();
        $this->pages();
        $this->translations();
        $this->translationKeys();
        $this->permissions();
        $this->migrations();
        $this->contracts();
        $this->exports();
        $this->widgets();

        return $this->issues;
    }

    /**
     * Imports of other modules that the module does not require. Core modules are always allowed. Tests, factories,
     * seeders and frontend files are ignored.
     *
     * @return array<string, list<string>> "Module -> Other" => the files that import the other module
     */
    public function boundaryViolations(): array
    {
        $folders = [];

        foreach ($this->registry->all() as $key => $provider) {
            $folders[$this->folderOf($provider)] = $key;
        }

        $violations = [];

        foreach ($this->registry->all() as $key => $provider) {
            $allowed = [$key => true];

            foreach ($this->registry->all() as $otherKey => $other) {
                if ($other->manifest()->core) {
                    $allowed[$otherKey] = true;
                }
            }

            foreach ($provider->manifest()->requires as $required) {
                $allowed[$required] = true;
            }

            foreach ($this->phpFiles($provider->modulePath(), ['Tests', 'Database', 'Resources']) as $path) {
                preg_match_all('/App\\\\Modules\\\\(\w+)\\\\/', (string) file_get_contents($path), $matches);

                foreach (array_unique($matches[1]) as $folder) {
                    $other = $folders[$folder] ?? null;

                    if ($other !== null && ! isset($allowed[$other])) {
                        $violations[$this->folderOf($provider).' -> '.$folder][] = basename($path);
                    }
                }
            }
        }

        ksort($violations);

        return $violations;
    }

    /**
     * The module's name in its namespace (App\Modules\<Name>\...), which is also the folder name when the module lives
     * in app/Modules but not when it comes from a Composer package.
     */
    private function folderOf(ModuleServiceProvider $provider): string
    {
        return explode('\\', $provider::class)[2] ?? basename($provider->modulePath());
    }

    private function manifests(): void
    {
        foreach ($this->registry->all() as $key => $provider) {
            $manifest = $provider->manifest();
            $folder = $this->folderOf($provider);

            if (! preg_match('/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/', $manifest->key)) {
                $this->add(self::ERROR, $key, "the key [{$manifest->key}] must be lowercase words joined by dashes");
            }

            if ($manifest->key !== str($folder)->kebab()->toString()) {
                $this->add(self::WARNING, $key, "the key [{$manifest->key}] does not match the folder name [{$folder}]");
            }

            if (trim($manifest->name) === '') {
                $this->add(self::ERROR, $key, 'the manifest has no name');
            }

            if (! preg_match('/^\d+\.\d+\.\d+$/', $manifest->version)) {
                $this->add(self::ERROR, $key, "the version [{$manifest->version}] is not major.minor.patch");
            }

            foreach ($manifest->packages as $package) {
                if (! InstalledVersions::isInstalled($package)) {
                    $this->add(self::ERROR, $key, "needs the composer package [{$package}]; run composer require {$package}");
                }
            }

            if (in_array($key, $manifest->requires, true)) {
                $this->add(self::ERROR, $key, 'requires itself');
            }

            if ($manifest->core && array_diff($manifest->requires, array_keys(array_filter($this->registry->all(), fn (ModuleServiceProvider $p): bool => $p->manifest()->core))) !== []) {
                $this->add(self::ERROR, $key, 'is a core module but requires a feature module');
            }
        }
    }

    private function domainPurity(): void
    {
        foreach ($this->registry->all() as $key => $provider) {
            foreach ($this->phpFiles($provider->modulePath(), ['Http', 'Routes', 'Resources', 'Tests', 'Database']) as $path) {
                if (str_ends_with($path, 'ServiceProvider.php')) {
                    continue;
                }

                if (preg_match('/use (Inertia\\\\|Illuminate\\\\Http\\\\Request;)/', (string) file_get_contents($path))) {
                    $this->add(self::ERROR, $key, basename($path).' is domain code but uses Inertia or an HTTP request; keep that in a controller');
                }
            }
        }
    }

    private function controllers(): void
    {
        foreach ($this->registry->all() as $key => $provider) {
            foreach (glob($provider->modulePath('Http/Controllers/*/*.php')) ?: [] as $controller) {
                if (preg_match('/DB::transaction|lockForUpdate/', (string) file_get_contents($controller))) {
                    $this->add(self::ERROR, $key, basename($controller).' runs a transaction; move it into an action');
                }
            }
        }
    }

    private function pages(): void
    {
        $seen = [];

        $roots = ['core' => resource_path('js/Pages')];

        foreach ($this->registry->all() as $key => $provider) {
            $roots[$key] = $provider->modulePath('Resources/js/Pages');
        }

        foreach ($roots as $owner => $root) {
            if (! is_dir($root)) {
                continue;
            }

            foreach ($this->filesIn($root, 'vue') as $path) {
                $name = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', $root)) + 1);

                if (isset($seen[$name])) {
                    $this->add(self::ERROR, $owner, "the page [{$name}] also exists in {$seen[$name]}");
                }

                $seen[$name] = $owner;
            }
        }
    }

    private function translations(): void
    {
        foreach ($this->registry->all() as $key => $provider) {
            $lang = $provider->modulePath('Resources/lang');

            if (! is_dir($lang)) {
                continue;
            }

            $this->compareLocales($key, $lang.'/en.json', $lang.'/ar.json', fn (string $file): array => array_keys(Arr::dot(json_decode((string) file_get_contents($file), true) ?? [])));

            $names = array_unique(array_map('basename', [...(glob($lang.'/en/*.php') ?: []), ...(glob($lang.'/ar/*.php') ?: [])]));

            foreach ($names as $name) {
                $this->compareLocales($key, "{$lang}/en/{$name}", "{$lang}/ar/{$name}", fn (string $file): array => array_keys(Arr::dot(require $file)));
            }
        }
    }

    /**
     * The translation keys a module's screens use must come from the core, the module itself or a module it requires.
     * Keys built at run time are not seen.
     */
    private function translationKeys(): void
    {
        $owners = [];

        foreach (config('tessera.translation_files', []) as $file) {
            foreach (is_file($file) ? array_keys(Arr::dot(json_decode((string) file_get_contents($file), true) ?? [])) : [] as $key) {
                $owners[$key] = 'core';
            }
        }

        foreach ($this->registry->all() as $moduleKey => $provider) {
            $file = $provider->modulePath('Resources/lang/en.json');

            foreach (is_file($file) ? array_keys(Arr::dot(json_decode((string) file_get_contents($file), true) ?? [])) : [] as $key) {
                $owners[$key] = $moduleKey;
            }
        }

        foreach ($this->registry->all() as $moduleKey => $provider) {
            $usable = ['core' => true, $moduleKey => true];

            foreach ($this->registry->all() as $otherKey => $other) {
                if ($other->manifest()->core) {
                    $usable[$otherKey] = true;
                }
            }

            foreach ($this->requiredBy($moduleKey) as $required) {
                $usable[$required] = true;
            }

            foreach ($this->frontendFiles($provider->modulePath('Resources/js')) as $path) {
                preg_match_all("/\bt\(\s*'([A-Za-z]+(?:\.[A-Za-z0-9_]+)+)'/", (string) file_get_contents($path), $matches);

                foreach (array_unique($matches[1]) as $key) {
                    $owner = $owners[$key] ?? null;

                    if ($owner === null) {
                        $this->add(self::ERROR, $moduleKey, basename($path)." uses the translation [{$key}], which nobody defines");
                    } elseif (! isset($usable[$owner])) {
                        $this->add(self::ERROR, $moduleKey, basename($path)." uses the translation [{$key}] of the {$owner} module without requiring it; move the text to the core or this module");
                    }
                }
            }
        }
    }

    /**
     * Every module the given one needs, directly or through others.
     *
     * @return list<string>
     */
    private function requiredBy(string $key): array
    {
        $found = [];
        $queue = $this->registry->all()[$key]->manifest()->requires;

        while ($queue !== []) {
            $next = array_shift($queue);

            if (isset($found[$next]) || ! isset($this->registry->all()[$next])) {
                continue;
            }

            $found[$next] = true;
            array_push($queue, ...$this->registry->all()[$next]->manifest()->requires);
        }

        return array_keys($found);
    }

    /**
     * Vue and JavaScript files below a folder, leaving tests out.
     *
     * @return list<string>
     */
    private function frontendFiles(string $root): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (in_array($file->getExtension(), ['vue', 'js'], true) && ! str_contains($path, '/__tests__/')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * @param  callable(string): list<string>  $keys
     */
    private function compareLocales(string $module, string $english, string $arabic, callable $keys): void
    {
        $present = array_values(array_filter([$english, $arabic], 'is_file'));

        if (count($present) === 1) {
            $this->add(self::ERROR, $module, basename(dirname($present[0])).'/'.basename($present[0]).' has no counterpart in the other language');

            return;
        }

        if ($present === []) {
            return;
        }

        $missing = array_diff($keys($english), $keys($arabic));
        $extra = array_diff($keys($arabic), $keys($english));

        foreach ($missing as $name) {
            $this->add(self::ERROR, $module, basename($english)." key [{$name}] is missing in Arabic");
        }

        foreach ($extra as $name) {
            $this->add(self::ERROR, $module, basename($arabic)." key [{$name}] is missing in English");
        }
    }

    private function permissions(): void
    {
        $declared = array_flip($this->registry->permissions());
        $features = $this->registry->features();

        foreach ($this->registry->all() as $key => $provider) {
            foreach (glob($provider->modulePath('Routes/*.php')) ?: [] as $routes) {
                $source = (string) file_get_contents($routes);

                preg_match_all("/can:([a-z][a-z ]*[a-z])['\"|,]/", $source, $permissions);

                foreach (array_unique($permissions[1]) as $permission) {
                    if (! isset($declared[$permission])) {
                        $this->add(self::ERROR, $key, basename($routes)." guards a route with [{$permission}], which no module declares");
                    }
                }

                preg_match_all("/module:([a-z-]+)['\"|,]/", $source, $guarded);

                foreach (array_unique($guarded[1]) as $feature) {
                    if (! isset($features[$feature])) {
                        $this->add(self::ERROR, $key, basename($routes)." guards a route with the plan feature [{$feature}], which no module declares");
                    }
                }
            }
        }
    }

    private function migrations(): void
    {
        $seen = [];

        $folders = ['database' => database_path('migrations')];

        foreach ($this->registry->all() as $key => $provider) {
            $folders[$key] = $provider->modulePath('Database/Migrations');
        }

        foreach ($folders as $owner => $folder) {
            foreach (glob($folder.'/*.php') ?: [] as $file) {
                $name = basename($file);

                if (isset($seen[$name])) {
                    $this->add(self::ERROR, $owner, "the migration [{$name}] also exists in {$seen[$name]}");
                }

                $seen[$name] = $owner;
            }
        }
    }

    private function contracts(): void
    {
        foreach (config('tessera.contracts', []) as $contract) {
            try {
                app()->make($contract);
            } catch (Throwable $e) {
                $this->add(self::ERROR, 'core', "the contract [{$contract}] cannot be resolved: ".$e->getMessage());
            }
        }
    }

    private function exports(): void
    {
        $keys = [];

        foreach ($this->registry->all() as $key => $provider) {
            foreach ($provider->exports() as $class) {
                if (! class_exists($class) || ! is_subclass_of($class, ReportDefinition::class)) {
                    $this->add(self::ERROR, $key, "the export [{$class}] is not a report definition");

                    continue;
                }

                $reportKey = app($class)->key();

                if (isset($keys[$reportKey])) {
                    $this->add(self::ERROR, $key, "the report key [{$reportKey}] is also used by {$keys[$reportKey]}");
                }

                $keys[$reportKey] = $key;
            }
        }
    }

    private function widgets(): void
    {
        foreach ($this->registry->all() as $key => $provider) {
            foreach ($provider->dashboardWidgets() as $class) {
                if (! class_exists($class) || ! is_subclass_of($class, DashboardWidget::class)) {
                    $this->add(self::ERROR, $key, "the dashboard widget [{$class}] does not implement DashboardWidget");

                    continue;
                }

                $component = app($class)->component();

                if (! is_file($provider->modulePath("Resources/js/Widgets/{$component}.vue"))) {
                    $this->add(self::ERROR, $key, "the dashboard widget [{$component}] has no Resources/js/Widgets/{$component}.vue");
                }
            }
        }
    }

    /**
     * @param  list<string>  $skipFolders  Folder names whose files are left out.
     * @return list<string>
     */
    private function phpFiles(string $root, array $skipFolders = []): array
    {
        $found = [];

        foreach ($this->filesIn($root, 'php') as $path) {
            $normal = str_replace('\\', '/', $path);

            foreach ($skipFolders as $skip) {
                if (str_contains($normal, "/{$skip}/")) {
                    continue 2;
                }
            }

            $found[] = $path;
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function filesIn(string $root, string $extension): array
    {
        $found = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === $extension) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    private function add(string $level, string $module, string $message): void
    {
        $this->issues[] = ['level' => $level, 'module' => $module, 'message' => $message];
    }
}
