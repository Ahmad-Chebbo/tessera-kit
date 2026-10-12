<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tessera\Engine\ModuleException;
use Tessera\Engine\ModulePublisher;

/**
 * Taking a module into the project so its code can be edited. The publisher works on the folders it is given, so these
 * tests use a temporary project and a made-up package instead of touching the real ones.
 */
function fakePackage(string $folder): void
{
    File::ensureDirectoryExists($folder.'/Models');
    File::ensureDirectoryExists($folder.'/Database/Migrations');
    File::ensureDirectoryExists($folder.'/Resources/js/Pages/Invoices/Partials');
    File::ensureDirectoryExists($folder.'/Tests/Feature');

    file_put_contents($folder.'/composer.json', json_encode(['name' => 'tessera/tessera-invoices', 'require' => ['php' => '^8.4']]));
    file_put_contents($folder.'/README.md', '# Invoices');
    file_put_contents($folder.'/InvoicesServiceProvider.php', '<?php // provider');
    file_put_contents($folder.'/Models/Invoice.php', '<?php // model');
    file_put_contents($folder.'/Database/Migrations/2026_01_01_000000_create_invoices_table.php', '<?php // migration');
    file_put_contents($folder.'/Resources/js/Pages/Invoices/Index.vue', '<template><div>module index</div></template>');
    file_put_contents($folder.'/Resources/js/Pages/Invoices/Partials/Row.vue', '<template><tr /></template>');
    file_put_contents($folder.'/Tests/Feature/InvoiceTest.php', '<?php // test');
}

function fakeProject(string $folder): string
{
    File::ensureDirectoryExists($folder);
    $composer = $folder.'/composer.json';
    file_put_contents($composer, json_encode([
        'name' => 'acme/shop',
        'require' => ['php' => '^8.4', 'tessera/tessera-invoices' => '^4.0', 'tessera/tessera-engine' => '^4.0'],
        'replace' => ['vendor/zzz' => '*'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    return $composer;
}

beforeEach(function () {
    $this->sandbox = sys_get_temp_dir().'/tessera-publish-'.uniqid();
    $this->package = $this->sandbox.'/vendor/invoices';
    fakePackage($this->package);
    $this->composer = fakeProject($this->sandbox.'/project');
    $this->publisher = new ModulePublisher(new Filesystem);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('copies the whole module into the project, without the package files', function () {
    $result = $this->publisher->eject($this->package, $this->sandbox.'/project/app/Modules/Invoices', $this->composer);

    $target = $this->sandbox.'/project/app/Modules/Invoices';

    expect($result)->toBe(['package' => 'tessera/tessera-invoices', 'files' => 6])
        ->and(is_file($target.'/InvoicesServiceProvider.php'))->toBeTrue()
        ->and(is_file($target.'/Models/Invoice.php'))->toBeTrue()
        ->and(is_file($target.'/Database/Migrations/2026_01_01_000000_create_invoices_table.php'))->toBeTrue()
        ->and(is_file($target.'/Resources/js/Pages/Invoices/Partials/Row.vue'))->toBeTrue()
        ->and(is_file($target.'/Tests/Feature/InvoiceTest.php'))->toBeTrue()
        ->and(file_exists($target.'/composer.json'))->toBeFalse()
        ->and(file_exists($target.'/README.md'))->toBeFalse();
});

it('makes composer.json provide the package instead of requiring it', function () {
    $this->publisher->eject($this->package, $this->sandbox.'/project/app/Modules/Invoices', $this->composer);

    $composer = json_decode(file_get_contents($this->composer), true);

    expect($composer['require'])->toBe(['php' => '^8.4', 'tessera/tessera-engine' => '^4.0'])
        ->and($composer['replace'])->toBe(['tessera/tessera-invoices' => '*', 'vendor/zzz' => '*'])
        ->and($composer['name'])->toBe('acme/shop');
});

it('does not replace what the project already has unless told to', function () {
    $target = $this->sandbox.'/project/app/Modules/Invoices';
    $this->publisher->eject($this->package, $target, $this->composer);
    file_put_contents($target.'/Models/Invoice.php', '<?php // my edit');

    expect(fn () => $this->publisher->eject($this->package, $target, $this->composer))->toThrow(ModuleException::class, 'already exists');
    expect(file_get_contents($target.'/Models/Invoice.php'))->toBe('<?php // my edit');

    $this->publisher->eject($this->package, $target, $this->composer, force: true);

    expect(file_get_contents($target.'/Models/Invoice.php'))->toBe('<?php // model');
});

it('refuses a module that is only part of a bigger package', function () {
    unlink($this->package.'/composer.json');

    expect(fn () => $this->publisher->eject($this->package, $this->sandbox.'/project/app/Modules/Invoices', $this->composer))
        ->toThrow(ModuleException::class, '--pages');

    expect(is_dir($this->sandbox.'/project/app/Modules/Invoices'))->toBeFalse()
        ->and(json_decode(file_get_contents($this->composer), true)['require'])->toHaveKey('tessera/tessera-invoices');
});

it('copies only the pages, keeping their folders, and keeps the ones the project has', function () {
    $pages = $this->sandbox.'/project/resources/js/Pages';
    File::ensureDirectoryExists($pages.'/Invoices');
    file_put_contents($pages.'/Invoices/Index.vue', '<template><div>mine</div></template>');

    $result = $this->publisher->pages($this->package, $pages);

    expect($result['copied'])->toBe(['Invoices/Partials/Row.vue'])
        ->and($result['skipped'])->toBe(['Invoices/Index.vue'])
        ->and(file_get_contents($pages.'/Invoices/Index.vue'))->toContain('mine');

    $forced = $this->publisher->pages($this->package, $pages, force: true);

    expect($forced['copied'])->toContain('Invoices/Index.vue')
        ->and(file_get_contents($pages.'/Invoices/Index.vue'))->toContain('module index');
});

it('has nothing to copy for a module without pages', function () {
    File::deleteDirectory($this->package.'/Resources');

    expect($this->publisher->pages($this->package, $this->sandbox.'/project/resources/js/Pages'))->toBe(['copied' => [], 'skipped' => []]);
});

it('tells the person who runs the command what is wrong', function () {
    $this->artisan('module:publish', ['key' => 'ghost'])->expectsOutputToContain('is not installed')->assertFailed();
    $this->artisan('module:publish', ['key' => 'team'])->expectsOutputToContain('bigger package')->assertFailed();
});

it('refuses a module that is already in the project', function () {
    removeScaffoldForPublish('Zzpublish');
    expect(Artisan::call('module:make', ['name' => 'Zzpublish']))->toBe(0);

    try {
        $run = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'module:publish', 'zzpublish']);

        expect($run->failed())->toBeTrue()
            ->and($run->output().$run->errorOutput())->toContain('already in your project');
    } finally {
        removeScaffoldForPublish('Zzpublish');
    }
});

it('does not call a project page that replaces a module page a clash', function () {
    removeScaffoldForPublish('Zzcheck');
    expect(Artisan::call('module:make', ['name' => 'Zzcheck']))->toBe(0);

    $override = resource_path('js/Pages/Zzcheck/Index.vue');
    File::ensureDirectoryExists(dirname($override));
    file_put_contents($override, '<template><div>mine</div></template>
');

    try {
        $run = Process::path(base_path())->run([PHP_BINARY, 'artisan', 'module:check']);

        expect($run->output().$run->errorOutput())->not->toContain('also exists in');
    } finally {
        File::deleteDirectory(dirname($override));
        removeScaffoldForPublish('Zzcheck');
    }
});

function removeScaffoldForPublish(string $name): void
{
    for ($attempt = 0; $attempt < 10 && is_dir(app_path("Modules/{$name}")); $attempt++) {
        File::deleteDirectory(app_path("Modules/{$name}"));

        if (is_dir(app_path("Modules/{$name}"))) {
            usleep(200_000);
        }
    }
}
