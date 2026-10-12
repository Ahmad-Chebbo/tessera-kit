<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tessera\Engine\ModuleChecker;
use Tessera\Engine\ModuleRegistry;

function artisanIn(array $arguments): array
{
    $result = Process::path(base_path())->run([PHP_BINARY, 'artisan', ...$arguments]);

    return [$result->exitCode(), $result->output().$result->errorOutput()];
}

/**
 * Removes a scaffolded module, trying again when Windows still holds a file open from the previous process.
 */
function removeScaffold(string $name): void
{
    for ($attempt = 0; $attempt < 10 && is_dir(app_path("Modules/{$name}")); $attempt++) {
        File::deleteDirectory(app_path("Modules/{$name}"));

        if (is_dir(app_path("Modules/{$name}"))) {
            usleep(200_000);
        }
    }
}

function scaffold(string $name, array $options = []): string
{
    removeScaffold($name);

    $code = Artisan::call('module:make', ['name' => $name, ...$options]);

    expect($code)->toBe(0, Artisan::output());

    return app_path("Modules/{$name}");
}

/**
 * Any module that is not core, for tests that need something to import or require.
 *
 * @return array{0: string, 1: string}|null The folder name and the key.
 */
function someFeatureModule(): ?array
{
    foreach (app(ModuleRegistry::class)->all() as $key => $provider) {
        if (! $provider->manifest()->core) {
            return [explode(chr(92), $provider::class)[2], $key];
        }
    }

    return null;
}

function importInto(string $controller, string $folder): void
{
    $import = 'use App'.chr(92).'Modules'.chr(92).$folder.chr(92).$folder.'ServiceProvider;';

    file_put_contents($controller, str_replace('use Inertia'.chr(92).'Inertia;', $import."\nuse Inertia".chr(92).'Inertia;', file_get_contents($controller)));
}

afterEach(function () {
    removeScaffold('Zzdemo');
});

describe('module:check', function () {
    it('finds nothing to complain about in the shipped modules', function () {
        expect(app(ModuleChecker::class)->run())->toBe([]);
    });

    it('reports translations that exist in one language only', function () {
        $module = scaffold('Zzdemo');
        $arabic = json_decode(file_get_contents("{$module}/Resources/lang/ar.json"), true);
        unset($arabic['zzdemo']['empty']);
        file_put_contents("{$module}/Resources/lang/ar.json", json_encode($arabic));

        [$code, $output] = artisanIn(['module:check']);

        expect($code)->toBe(1)->and($output)->toContain('zzdemo.empty')->toContain('missing in Arabic');
    });

    it('reports routes guarded by a permission nobody declares', function () {
        $module = scaffold('Zzdemo');
        file_put_contents("{$module}/Routes/web.php", str_replace('can:view zzdemo', 'can:view ghosts', file_get_contents("{$module}/Routes/web.php")));

        [$code, $output] = artisanIn(['module:check']);

        expect($code)->toBe(1)->and($output)->toContain('[view ghosts]');
    });

    it('reports imports of modules that are not required', function () {
        [$folder] = someFeatureModule();
        $module = scaffold('Zzdemo');
        $controller = "{$module}/Http/Controllers/Web/ZzdemoController.php";
        importInto($controller, $folder);

        [$code, $output] = artisanIn(['module:check']);

        expect($code)->toBe(1)->and($output)->toContain("uses the {$folder} module");
    })->skip(fn () => someFeatureModule() === null, 'No feature module is installed');

    it('accepts the import once the module requires the other one', function () {
        [$folder, $key] = someFeatureModule();
        $module = scaffold('Zzdemo', ['--requires' => [$key]]);
        $controller = "{$module}/Http/Controllers/Web/ZzdemoController.php";
        importInto($controller, $folder);

        expect(artisanIn(['module:check'])[0])->toBe(0);
    })->skip(fn () => someFeatureModule() === null, 'No feature module is installed');

    it('reports a page that two modules both provide', function () {
        $module = scaffold('Zzdemo');
        File::ensureDirectoryExists("{$module}/Resources/js/Pages/Auth");
        file_put_contents("{$module}/Resources/js/Pages/Auth/Login.vue", '<template><div /></template>');

        [$code, $output] = artisanIn(['module:check']);

        expect($code)->toBe(1)->and($output)->toContain('Auth/Login.vue');
    });
});

describe('module:make', function () {
    it('creates a working module that can be dropped in without touching anything else', function () {
        $module = scaffold('Zzdemo');

        foreach (['ZzdemoServiceProvider.php', 'Routes/web.php', 'Http/Controllers/Web/ZzdemoController.php', 'Resources/js/Pages/Zzdemo/Index.vue', 'Resources/lang/en.json', 'Resources/lang/ar.json', 'Tests/Feature/ZzdemoTest.php', 'Database/Migrations/.gitkeep'] as $file) {
            expect(is_file("{$module}/{$file}"))->toBeTrue($file);
        }

        [$code, $output] = artisanIn(['module:list']);
        expect($code)->toBe(0)->and($output)->toContain('zzdemo');

        expect(artisanIn(['module:check'])[0])->toBe(0);

        [$code, $output] = artisanIn(['test', '--compact', 'app/Modules/Zzdemo']);
        expect($code)->toBe(0, $output);
    });

    it('turns the name into the key, permission and routes', function () {
        $module = scaffold('Zzdemo');
        $provider = file_get_contents("{$module}/ZzdemoServiceProvider.php");

        expect($provider)->toContain("key: 'zzdemo'")->toContain("'view zzdemo'")->toContain("'zzdemo.index'")
            ->and(file_get_contents("{$module}/Routes/web.php"))->toContain("'/zzdemo'")
            ->and(json_decode(file_get_contents("{$module}/Resources/lang/en.json"), true))->toHaveKeys(['zzdemo', 'roles']);
    });

    it('refuses bad names and modules that already exist', function () {
        $this->artisan('module:make', ['name' => 'not valid'])->assertFailed();
        $this->artisan('module:make', ['name' => 'Team'])->assertFailed();
    });
});
