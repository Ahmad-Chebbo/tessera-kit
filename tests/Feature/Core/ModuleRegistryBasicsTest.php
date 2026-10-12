<?php

use Tessera\Engine\ModuleException;
use Tessera\Engine\ModuleManifest;
use Tessera\Engine\ModuleRegistry;
use Tessera\Engine\ModuleServiceProvider;

function basicsModule(string $key, array $requires = []): ModuleServiceProvider
{
    $holder = new class($key, $requires)
    {
        public function __construct(public string $key, public array $requires) {}
    };

    return new class(app(), $holder) extends ModuleServiceProvider
    {
        public function __construct($app, private object $holder)
        {
            parent::__construct($app);
        }

        public function manifest(): ModuleManifest
        {
            return new ModuleManifest(key: $this->holder->key, name: $this->holder->key, requires: $this->holder->requires);
        }
    };
}

it('finds the core modules and enables them', function () {
    $registry = app(ModuleRegistry::class);

    foreach (['auth', 'company', 'subscription', 'team'] as $core) {
        expect($registry->has($core))->toBeTrue()->and($registry->get($core)->manifest()->core)->toBeTrue();
    }
});

it('lists requirements before the modules that need them', function () {
    $order = array_keys(app(ModuleRegistry::class)->enabled());

    foreach (app(ModuleRegistry::class)->enabled() as $key => $provider) {
        foreach ($provider->manifest()->requires as $required) {
            expect(array_search($required, $order, true))->toBeLessThan(array_search($key, $order, true));
        }
    }
});

it('refuses a requirement that is not installed at all', function () {
    new ModuleRegistry([basicsModule('alpha', ['ghost'])]);
})->throws(ModuleException::class, 'The module [alpha] requires [ghost], which is not installed.');

it('refuses modules that require each other in a circle', function () {
    new ModuleRegistry([basicsModule('alpha', ['beta']), basicsModule('beta', ['alpha'])]);
})->throws(ModuleException::class, 'Modules require each other in a circle: alpha -> beta -> alpha.');

it('refuses two modules with the same key', function () {
    new ModuleRegistry([basicsModule('alpha'), basicsModule('alpha')]);
})->throws(ModuleException::class, 'Two modules declare the key [alpha].');

it('refuses to enable a module that is not installed', function () {
    new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), 'invoices');
})->throws(ModuleException::class, 'The module [invoices] is enabled but not installed.');

it('keeps core modules enabled whatever is asked for', function () {
    $registry = new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), 'none');

    expect(array_keys($registry->enabled()))->toEqualCanonicalizing(['auth', 'company', 'subscription', 'team']);
});

it('turns the web layer off without touching the api layer', function () {
    $registry = new ModuleRegistry(array_values(app(ModuleRegistry::class)->all()), '*', ['api']);

    expect($registry->webEnabled())->toBeFalse()->and($registry->apiEnabled())->toBeTrue()->and($registry->layers())->toBe(['api']);
});
