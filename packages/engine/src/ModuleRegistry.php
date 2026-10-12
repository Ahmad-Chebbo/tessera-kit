<?php

namespace Tessera\Engine;

use App\Modules\Team\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Tessera\Engine\Dashboard\DashboardWidget;
use Tessera\Engine\Exports\ReportDefinition;

/**
 * The modules that are installed and enabled in this project, in an order that respects their requirements.
 */
final class ModuleRegistry
{
    /**
     * @var array<string, ModuleServiceProvider>
     */
    private array $discovered = [];

    /**
     * @var array<string, ModuleServiceProvider>
     */
    private array $enabled = [];

    /**
     * @var list<string>
     */
    private array $layers;

    /**
     * @var array<string, PlanFeature>|null
     */
    private ?array $features = null;

    /**
     * @var array<string, string>|null
     */
    private ?array $permissionFeatures = null;

    /**
     * @param  list<ModuleServiceProvider>  $providers
     * @param  string|list<string>  $enabled  "*", a comma separated string of module keys or a list of keys.
     * @param  list<string>  $layers
     */
    public function __construct(array $providers, string|array $enabled = '*', array $layers = ['web', 'api'])
    {
        $this->layers = array_values($layers);

        foreach ($providers as $provider) {
            $key = $provider->manifest()->key;

            if (isset($this->discovered[$key])) {
                throw new ModuleException("Two modules declare the key [{$key}].");
            }

            $this->discovered[$key] = $provider;
        }

        $this->enabled = $this->resolve($this->enabledKeys($enabled));
    }

    /**
     * @return array<string, ModuleServiceProvider> Every module found, enabled or not.
     */
    public function all(): array
    {
        return $this->discovered;
    }

    /**
     * @return array<string, ModuleServiceProvider> Enabled modules, requirements first.
     */
    public function enabled(): array
    {
        return $this->enabled;
    }

    public function has(string $key): bool
    {
        return isset($this->enabled[$key]);
    }

    public function get(string $key): ModuleServiceProvider
    {
        return $this->enabled[$key] ?? throw new ModuleException("The module [{$key}] is not enabled.");
    }

    /**
     * @return list<string>
     */
    public function layers(): array
    {
        return $this->layers;
    }

    public function webEnabled(): bool
    {
        return in_array('web', $this->layers, true) && class_exists(Inertia::class);
    }

    public function apiEnabled(): bool
    {
        return in_array('api', $this->layers, true);
    }

    /**
     * The route files with this name (web.php, guest.php, api.php) of the enabled modules, in module name order.
     *
     * @return list<string>
     */
    public function routeFiles(string $file): array
    {
        $providers = $this->enabled;

        uasort($providers, fn (ModuleServiceProvider $a, ModuleServiceProvider $b): int => strcmp($a::class, $b::class));

        return array_values(array_filter(
            array_map(fn (ModuleServiceProvider $provider): string => $provider->modulePath('Routes/'.$file), $providers),
            'is_file',
        ));
    }

    /**
     * Plan features of the enabled modules, merged by key and ordered for plan screens.
     *
     * @return array<string, PlanFeature>
     */
    public function features(): array
    {
        if ($this->features !== null) {
            return $this->features;
        }

        $merged = [];

        foreach ($this->enabled as $provider) {
            foreach ($provider->features() as $feature) {
                $merged[$feature->key] = isset($merged[$feature->key])
                    ? new PlanFeature($feature->key, $merged[$feature->key]->label, array_values(array_unique([...$merged[$feature->key]->permissions, ...$feature->permissions])), $merged[$feature->key]->order)
                    : $feature;
            }
        }

        uasort($merged, fn (PlanFeature $a, PlanFeature $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        return $this->features = $merged;
    }

    /**
     * The plan feature that switches a permission, or null when the permission is always on.
     */
    public function featureForPermission(string $permission): ?string
    {
        if ($this->permissionFeatures === null) {
            $this->permissionFeatures = [];

            foreach ($this->features() as $feature) {
                foreach ($feature->permissions as $name) {
                    $this->permissionFeatures[$name] ??= $feature->key;
                }
            }
        }

        return $this->permissionFeatures[$permission] ?? null;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function featureOptions(): array
    {
        return array_values(array_map(fn (PlanFeature $feature): array => ['value' => $feature->key, 'label' => $feature->label], $this->features()));
    }

    /**
     * Every permission the enabled modules declare.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        $names = [];

        foreach ($this->enabled as $provider) {
            $names = [...$names, ...$provider->permissions()];
        }

        return array_values(array_unique($names));
    }

    /**
     * Role editor groups merged across modules, in display order.
     *
     * @return array<string, list<string>>
     */
    public function permissionGroups(): array
    {
        $groups = [];

        foreach ($this->enabled as $provider) {
            foreach ($provider->permissionGroups() as $group => $permissions) {
                $groups[$group] = array_values(array_unique([...($groups[$group] ?? []), ...$permissions]));
            }
        }

        $known = array_flip(MenuGroups::PERMISSION_ORDER);

        uksort($groups, fn (string $a, string $b): int => ($known[$a] ?? PHP_INT_MAX) <=> ($known[$b] ?? PHP_INT_MAX));

        return $groups;
    }

    /**
     * Permissions the default roles receive, merged across modules: role name => permissions.
     *
     * @return array<string, list<string>>
     */
    public function defaultRolePermissions(): array
    {
        $roles = [];

        foreach ($this->enabled as $provider) {
            foreach ($provider->defaultRolePermissions() as $role => $permissions) {
                $roles[$role] = array_values(array_unique([...($roles[$role] ?? []), ...$permissions]));
            }
        }

        return $roles;
    }

    /**
     * Sidebar entries of the enabled modules, ordered by section and then by their own order.
     *
     * @return list<MenuItem>
     */
    public function menuItems(): array
    {
        $items = [];

        foreach ($this->enabled as $provider) {
            array_push($items, ...$provider->menu());
        }

        $groups = array_flip(array_keys(MenuGroups::TITLES));

        usort($items, fn (MenuItem $a, MenuItem $b): int => [$groups[$a->group] ?? PHP_INT_MAX, $a->order] <=> [$groups[$b->group] ?? PHP_INT_MAX, $b->order]);

        return $items;
    }

    /**
     * Report definitions the enabled modules offer for export.
     *
     * @return list<class-string<ReportDefinition>>
     */
    public function exports(): array
    {
        $reports = [];

        foreach ($this->enabled as $provider) {
            array_push($reports, ...$provider->exports());
        }

        return $reports;
    }

    /**
     * Seeders of the enabled modules, requirements first.
     *
     * @return list<class-string<Seeder>>
     */
    public function seeders(): array
    {
        $seeders = [];

        foreach ($this->enabled as $provider) {
            array_push($seeders, ...$provider->seeders());
        }

        return $seeders;
    }

    /**
     * Dashboard widgets of the enabled modules, in display order.
     *
     * @return list<DashboardWidget>
     */
    public function dashboardWidgets(): array
    {
        $widgets = [];

        foreach ($this->enabled as $provider) {
            foreach ($provider->dashboardWidgets() as $class) {
                $widgets[] = app($class);
            }
        }

        usort($widgets, fn ($a, $b): int => $a->order() <=> $b->order());

        return $widgets;
    }

    /**
     * Inertia shared props the enabled modules add.
     *
     * @return array<string, \Closure(Request, ?User): mixed>
     */
    public function sharedProps(): array
    {
        $props = [];

        foreach ($this->enabled as $provider) {
            $props = [...$props, ...$provider->sharedProps()];
        }

        return $props;
    }

    /**
     * @param  string|list<string>  $enabled
     * @return list<string>|null Null means every module.
     */
    private function enabledKeys(string|array $enabled): ?array
    {
        if (is_string($enabled)) {
            $enabled = array_map('trim', explode(',', $enabled));
        }

        $keys = array_values(array_filter($enabled, fn (string $key): bool => $key !== ''));

        if ($keys === ['none']) {
            return [];
        }

        return $keys === [] || in_array('*', $keys, true) ? null : $keys;
    }

    /**
     * @param  list<string>|null  $keys
     * @return array<string, ModuleServiceProvider>
     */
    private function resolve(?array $keys): array
    {
        foreach ($keys ?? [] as $key) {
            if (! isset($this->discovered[$key])) {
                throw new ModuleException("The module [{$key}] is enabled but not installed.");
            }
        }

        $wanted = array_filter(
            $this->discovered,
            fn (ModuleServiceProvider $provider, string $key): bool => $keys === null || $provider->manifest()->core || in_array($key, $keys, true),
            ARRAY_FILTER_USE_BOTH,
        );

        $ordered = [];
        $visiting = [];

        $visit = function (string $key, array $path) use (&$visit, &$ordered, &$visiting, $wanted): void {
            if (isset($ordered[$key])) {
                return;
            }

            if (isset($visiting[$key])) {
                throw new ModuleException('Modules require each other in a circle: '.implode(' -> ', [...$path, $key]).'.');
            }

            $visiting[$key] = true;

            foreach ($wanted[$key]->manifest()->requires as $required) {
                if (! isset($wanted[$required])) {
                    throw new ModuleException("The module [{$key}] requires [{$required}], which is not ".(isset($this->discovered[$required]) ? 'enabled' : 'installed').'.');
                }

                $visit($required, [...$path, $key]);
            }

            unset($visiting[$key]);
            $ordered[$key] = $wanted[$key];
        };

        foreach (array_keys($wanted) as $key) {
            $visit($key, []);
        }

        return $ordered;
    }
}
