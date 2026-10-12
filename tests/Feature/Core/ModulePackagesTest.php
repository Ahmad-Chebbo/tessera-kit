<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tessera\Engine\ComposerModules;

function packageIn(array $arguments): int
{
    return Process::path(base_path())->run([PHP_BINARY, 'artisan', 'module:package', ...$arguments])->exitCode();
}

function installedJson(array $packages): string
{
    $file = tempnam(sys_get_temp_dir(), 'installed').'.json';
    file_put_contents($file, json_encode(['packages' => $packages]));

    return $file;
}

describe('finding modules installed with Composer', function () {
    it('collects the providers packages advertise and ignores the rest', function () {
        $file = installedJson([
            ['name' => 'tessera/tessera-invoices', 'extra' => ['tessera' => ['modules' => ['App\\Modules\\Invoices\\InvoicesServiceProvider']]]],
            ['name' => 'laravel/framework', 'extra' => ['branch-alias' => ['dev-master' => '13.x-dev']]],
            ['name' => 'tessera/tessera-refunds', 'extra' => ['tessera' => ['modules' => ['App\\Modules\\Refunds\\RefundsServiceProvider', 'App\\Modules\\Invoices\\InvoicesServiceProvider']]]],
        ]);

        expect((new ComposerModules)->classes($file))->toBe(['App\\Modules\\Invoices\\InvoicesServiceProvider', 'App\\Modules\\Refunds\\RefundsServiceProvider']);
    });

    it('returns nothing when Composer has installed nothing yet', function () {
        expect((new ComposerModules)->classes(sys_get_temp_dir().'/does-not-exist.json'))->toBe([]);
    });

    it('keeps the answer until installed.json changes', function () {
        $file = installedJson([['name' => 'tessera/tessera-a', 'extra' => ['tessera' => ['modules' => ['A\\Provider']]]]]);
        $cache = tempnam(sys_get_temp_dir(), 'cache').'.php';
        $modules = new ComposerModules;

        expect($modules->classes($file, $cache))->toBe(['A\\Provider']);

        $stamp = filemtime($file);
        file_put_contents($file, json_encode(['packages' => []]));
        touch($file, $stamp);
        expect($modules->classes($file, $cache))->toBe(['A\\Provider']);

        touch($file, $stamp + 10);
        expect($modules->classes($file, $cache))->toBe([]);
    });
});

describe('module:package', function () {
    afterEach(function () {
        File::deleteDirectory(app_path('Modules/Zzdemo'));
        File::deleteDirectory(base_path('packages-test'));
    });

    it('writes a package that keeps the module namespace and lists its provider', function () {
        $this->artisan('module:make', ['name' => 'Zzdemo'])->assertSuccessful();

        expect(packageIn(['zzdemo', '--path=packages-test']))->toBe(0);

        $package = base_path('packages-test/zzdemo');
        $composer = json_decode(file_get_contents("{$package}/composer.json"), true);

        expect($composer['name'])->toBe('tessera/tessera-zzdemo')
            ->and($composer['autoload']['psr-4'])->toBe(['App\\Modules\\Zzdemo\\' => ''])
            ->and($composer['extra']['tessera']['modules'])->toBe(['App\\Modules\\Zzdemo\\ZzdemoServiceProvider'])
            ->and($composer['require'])->toHaveKeys(['php', 'tessera/tessera-engine', 'tessera/tessera-base'])
            ->and(is_file("{$package}/ZzdemoServiceProvider.php"))->toBeTrue()
            ->and(is_file("{$package}/Resources/js/Pages/Zzdemo/Index.vue"))->toBeTrue();
    });

    it('lists the other modules and composer packages the module needs', function () {
        $this->artisan('module:package', ['key' => 'exports', '--path' => 'packages-test'])->assertSuccessful();

        $composer = json_decode(file_get_contents(base_path('packages-test/exports/composer.json')), true);

        expect($composer['require'])->toHaveKeys(['mpdf/mpdf', 'openspout/openspout'])
            ->and($composer['version'])->toBe('1.0.0');

        $this->artisan('module:package', ['key' => 'sales', '--path' => 'packages-test'])->assertSuccessful();
        $sales = json_decode(file_get_contents(base_path('packages-test/sales/composer.json')), true);

        expect($sales['require'])->toHaveKeys(['tessera/tessera-catalog', 'tessera/tessera-inventory', 'tessera/tessera-customers'])->not->toHaveKey('tessera/tessera-team')
            ->and($sales['require'])->toHaveKey('tessera/tessera-tenant')->not->toHaveKey('tessera/tessera-saas')
            ->and($composer['require'])->not->toHaveKey('tessera/tessera-tenant');
    })->skip(fn () => ! moduleInstalled('exports') || ! moduleInstalled('sales'), 'Needs Exports and Sales');

    it('refuses core modules, unknown keys and existing folders', function () {
        $this->artisan('module:package', ['key' => 'team'])->assertFailed();
        $this->artisan('module:package', ['key' => 'ghost'])->assertFailed();

        $this->artisan('module:make', ['name' => 'Zzdemo'])->assertSuccessful();
        expect(packageIn(['zzdemo', '--path=packages-test']))->toBe(0)
            ->and(packageIn(['zzdemo', '--path=packages-test']))->toBe(1)
            ->and(packageIn(['zzdemo', '--path=packages-test', '--force']))->toBe(0);
    });
});
