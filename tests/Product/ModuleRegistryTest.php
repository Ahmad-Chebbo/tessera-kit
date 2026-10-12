<?php

use App\Modules\Company\Services\CurrencyService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\ServiceProvider;
use Tessera\Engine\ModuleException;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleRegistry;
use Tessera\Engine\ModuleServiceProvider;

function fakeModule(string $key, array $requires = [], bool $core = false): ModuleServiceProvider
{
    $provider = new class(app()) extends ModuleServiceProvider
    {
        public ?ManifestHolder $holder = null;

        public function manifest(): ModuleManifest
        {
            return new ModuleManifest(key: $this->holder->key, name: $this->holder->key, requires: $this->holder->requires, core: $this->holder->core);
        }
    };

    $provider->holder = new ManifestHolder($key, $requires, $core);

    return $provider;
}

final class ManifestHolder
{
    public function __construct(public string $key, public array $requires, public bool $core) {}
}

function artisanRoutes(array $env): array
{
    $result = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'route:list', '--json']);

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

    return json_decode($result->output(), true);
}

describe('discovery', function () {
    it('finds every module of the application and enables them all by default', function () {
        $registry = app(ModuleRegistry::class);

        expect($registry->all())->toHaveCount(14)
            ->and($registry->enabled())->toHaveCount(14)
            ->and($registry->has('expenses'))->toBeTrue()
            ->and($registry->get('sales')->manifest()->key)->toBe('sales');
    });

    it('lists requirements before the modules that need them', function () {
        $order = array_keys(app(ModuleRegistry::class)->enabled());

        foreach (app(ModuleRegistry::class)->enabled() as $key => $provider) {
            foreach ($provider->manifest()->requires as $required) {
                expect(array_search($required, $order, true))->toBeLessThan(array_search($key, $order, true));
            }
        }
    });

    it('finds the route files of the enabled modules in module name order', function () {
        $files = app(ModuleRegistry::class)->routeFiles('web.php');

        expect($files)->toHaveCount(14)
            ->and(array_map(fn (string $file): string => basename(dirname($file, 2)), $files))->toBe(collect(moduleFolders())->keys()->map(fn (string $name): string => basename(moduleFolders()[$name]))->sortBy(fn (string $name): string => strtolower($name))->values()->all())
            ->and(app(ModuleRegistry::class)->routeFiles('guest.php'))->toHaveCount(1);
    });
});

describe('enabling', function () {
    it('leaves out disabled modules but never core modules', function () {
        $registry = new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), 'catalog,inventory');

        expect(array_keys($registry->enabled()))->toContain('catalog', 'inventory', 'company', 'team', 'auth', 'subscription')
            ->not->toContain('expenses', 'sales')
            ->and(array_filter($registry->routeFiles('web.php'), fn (string $file): bool => stripos($file, 'expenses') !== false))->toBe([]);
    });

    it('refuses a module whose requirement is not enabled', function () {
        new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), 'attendance');
    })->throws(ModuleException::class, 'The module [attendance] requires [shifts], which is not enabled.');

    it('refuses to enable a module that is not installed', function () {
        new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), 'invoices');
    })->throws(ModuleException::class, 'The module [invoices] is enabled but not installed.');

    it('refuses a requirement that is not installed at all', function () {
        new ModuleRegistry([fakeModule('alpha', ['ghost'])]);
    })->throws(ModuleException::class, 'The module [alpha] requires [ghost], which is not installed.');

    it('refuses modules that require each other in a circle', function () {
        new ModuleRegistry([fakeModule('alpha', ['beta']), fakeModule('beta', ['alpha'])]);
    })->throws(ModuleException::class, 'Modules require each other in a circle: alpha -> beta -> alpha.');

    it('refuses two modules with the same key', function () {
        new ModuleRegistry([fakeModule('alpha'), fakeModule('alpha')]);
    })->throws(ModuleException::class, 'Two modules declare the key [alpha].');

    it('accepts the enabled keys as a list too', function () {
        $registry = new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), ['catalog']);

        expect($registry->has('catalog'))->toBeTrue()->and($registry->has('sales'))->toBeFalse();
    });
});

describe('layers', function () {
    it('serves the web and api layers by default', function () {
        $registry = app(ModuleRegistry::class);

        expect($registry->webEnabled())->toBeTrue()->and($registry->apiEnabled())->toBeTrue();
    });

    it('turns the web layer off without touching the api layer', function () {
        $registry = new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), '*', ['api']);

        expect($registry->webEnabled())->toBeFalse()->and($registry->apiEnabled())->toBeTrue()->and($registry->layers())->toBe(['api']);
    });

    it('skips Inertia when the web layer is off', function () {
        $this->app->instance(ModuleRegistry::class, new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), '*', ['api']));

        $this->get('/up')->assertOk()->assertHeaderMissing('Vary', 'X-Inertia');
    });
});

describe('the booted application', function () {
    it('registers the same routes as before modules were discovered', function () {
        $routes = artisanRoutes([]);
        $names = collect($routes)->pluck('name')->filter();

        expect($names)->toContain('login', 'dashboard', 'expenses.index', 'pos.index', 'users.index');
    });

    it('registers no routes of a disabled module', function () {
        $routes = artisanRoutes(['MODULES_ENABLED' => 'catalog,inventory']);
        $names = collect($routes)->pluck('name')->filter();

        expect($names)->toContain('products.index', 'inventory.index', 'users.index')
            ->not->toContain('expenses.index', 'pos.index', 'attendance.index');
    });

    it('registers no module web routes when only the api layer is on', function () {
        $routes = artisanRoutes(['MODULES_LAYERS' => 'api']);
        $names = collect($routes)->pluck('name')->filter();

        expect($names)->not->toContain('login', 'dashboard', 'expenses.index');
    });

    it('fails to boot when a requirement is missing', function () {
        $result = Process::path(base_path())->env(['MODULES_ENABLED' => 'attendance'])->run([PHP_BINARY, 'artisan', 'route:list']);

        expect($result->failed())->toBeTrue()->and($result->errorOutput().$result->output())->toContain('requires [shifts]');
    });

    it('lists the modules in the console', function () {
        $this->artisan('module:list')->expectsOutputToContain('expenses')->assertSuccessful();
    });
});

describe('publishable config', function () {
    it('lets a project publish the engine and module configs under one tag', function () {
        $published = array_map(fn (string $target): string => basename($target), ServiceProvider::pathsToPublish(null, 'tessera-config'));

        expect(array_values($published))->toContain('tessera.php', 'modules.php', 'exports.php', 'currencies.php')
            ->and(array_values(ServiceProvider::pathsToPublish(null, 'tessera-exports-config')))->toHaveCount(1)
            ->and(array_values(ServiceProvider::pathsToPublish(null, 'tessera-engine-config')))->toHaveCount(2);
    });
});

describe('currencies', function () {
    it('formats amounts with the configured currencies', function () {
        $currency = new CurrencyService('LBP');

        expect($currency->format(1500000))->toBe('1,500,000 ل.ل')
            ->and((new CurrencyService('USD'))->format(1234.5))->toBe('$1,234.50')
            ->and(CurrencyService::getCurrencyInfo('KWD')['decimals'])->toBe(3);
    });
});
