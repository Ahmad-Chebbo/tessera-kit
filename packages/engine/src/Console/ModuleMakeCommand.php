<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Tessera\Engine\ModuleRegistry;

class ModuleMakeCommand extends Command
{
    protected $signature = 'module:make {name : The module name in StudlyCase, for example Invoices}
        {--requires=* : Keys of the modules it needs, besides the core ones}
        {--group=main : The sidebar group of its menu entry}
        {--force : Overwrite files of an existing module}';

    protected $description = 'Scaffold a module with a provider, a page, translations and a test';

    public function handle(Filesystem $files): int
    {
        $name = (string) $this->argument('name');

        if (! preg_match('/^[A-Z][A-Za-z0-9]*$/', $name)) {
            $this->components->error('Use a StudlyCase name made of letters and digits, for example Invoices.');

            return self::FAILURE;
        }

        $base = app_path("Modules/{$name}");

        if (($files->isDirectory($base) || app(ModuleRegistry::class)->has(Str::kebab($name))) && ! $this->option('force')) {
            $this->components->error("The module [{$name}] already exists. Use --force to overwrite its files.");

            return self::FAILURE;
        }

        $key = Str::kebab($name);
        $tokens = [
            '{{Studly}}' => $name,
            '{{key}}' => $key,
            '{{camel}}' => Str::camel($name),
            '{{label}}' => Str::headline($name),
            '{{words}}' => str_replace('-', ' ', $key),
            '{{group}}' => (string) $this->option('group'),
            '{{requires}}' => implode(', ', array_map(fn (string $required): string => "'{$required}'", array_filter((array) $this->option('requires')))),
        ];

        $stubs = __DIR__.'/../Stubs';
        $written = [
            "{$name}ServiceProvider.php" => $files->get("{$stubs}/provider.stub"),
            "Http/Controllers/Web/{$name}Controller.php" => $files->get("{$stubs}/controller.stub"),
            'Routes/web.php' => $files->get("{$stubs}/routes.stub"),
            "Resources/js/Pages/{$name}/Index.vue" => $files->get("{$stubs}/page.stub"),
            "Tests/Feature/{$name}Test.php" => $files->get("{$stubs}/test.stub"),
            'Resources/lang/en.json' => $this->translations($tokens['{{label}}'], $key, [
                'title' => $tokens['{{label}}'], 'subtitle' => "Manage {$tokens['{{words}}']}.", 'empty' => 'Nothing here yet.', 'nav' => $tokens['{{label}}'],
            ]),
            'Resources/lang/ar.json' => $this->translations($tokens['{{label}}'], $key, [
                'title' => $tokens['{{label}}'], 'subtitle' => $tokens['{{label}}'], 'empty' => '-', 'nav' => $tokens['{{label}}'],
            ]),
            'Database/Migrations/.gitkeep' => '',
        ];

        foreach ($written as $path => $contents) {
            $target = "{$base}/{$path}";
            $files->ensureDirectoryExists(dirname($target));
            $files->put($target, strtr($contents, $tokens));
            $this->components->twoColumnDetail("app/Modules/{$name}/{$path}", '<fg=green>created</>');
        }

        $this->newLine();
        $this->line('Next: run <info>composer dump-autoload</info>, <info>php artisan modules:sync</info> and <info>php artisan module:check</info>, then <info>bun run build</info>.');
        $this->line('Translate the Arabic strings in Resources/lang/ar.json, and the role editor group label (roles.groups.'.$key.') if the module has permissions.');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $strings
     */
    private function translations(string $label, string $key, array $strings): string
    {
        return json_encode([
            Str::camel($key) => $strings,
            'roles' => ['groups' => [$key => $label]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
    }
}
