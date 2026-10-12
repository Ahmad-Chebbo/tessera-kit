<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use ReflectionClass;
use Tessera\Engine\ModuleException;
use Tessera\Engine\ModulePublisher;
use Tessera\Engine\ModuleRegistry;

class ModulePublishCommand extends Command
{
    protected $signature = 'module:publish {key : The key of the module, for example catalog}
        {--pages : Copy only the Vue pages, which then replace the module\'s pages of the same name}
        {--force : Replace what the project already has}
        {--no-composer : Edit composer.json but do not run Composer}';

    protected $description = 'Take a module into your project so its code can be edited: models, migrations, pages, translations and tests';

    public function handle(ModuleRegistry $registry, ModulePublisher $publisher): int
    {
        $key = (string) $this->argument('key');
        $provider = $registry->all()[$key] ?? null;

        if ($provider === null) {
            $this->components->error("The module [{$key}] is not installed.");

            return self::FAILURE;
        }

        $source = $provider->modulePath();
        $name = basename(str_replace('\\', '/', (new ReflectionClass($provider))->getNamespaceName()));

        if (str_starts_with(str_replace('\\', '/', $source), str_replace('\\', '/', app_path('Modules')).'/')) {
            $this->components->error("The module [{$key}] is already in your project, in app/Modules/{$name}.");

            return self::FAILURE;
        }

        try {
            return $this->option('pages') ? $this->publishPages($publisher, $source) : $this->eject($publisher, $source, $name);
        } catch (ModuleException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function publishPages(ModulePublisher $publisher, string $source): int
    {
        $result = $publisher->pages($source, resource_path('js/Pages'), (bool) $this->option('force'));

        foreach ($result['copied'] as $page) {
            $this->components->twoColumnDetail($page, '<fg=green>copied</>');
        }

        foreach ($result['skipped'] as $page) {
            $this->components->twoColumnDetail($page, '<fg=yellow>kept, the project already has it</>');
        }

        if ($result['copied'] === [] && $result['skipped'] === []) {
            $this->components->warn('The module has no pages.');

            return self::SUCCESS;
        }

        $this->components->info('The pages are in resources/js/Pages and replace the module\'s pages of the same name. Edit them, then run bun run build.');
        $this->line('<fg=gray>The module itself keeps updating through Composer. Your copies do not: compare them with the module\'s pages after an update.</>');

        return self::SUCCESS;
    }

    private function eject(ModulePublisher $publisher, string $source, string $name): int
    {
        $result = $publisher->eject($source, app_path("Modules/{$name}"), base_path('composer.json'), (bool) $this->option('force'));

        $this->components->info("Copied {$result['files']} files to app/Modules/{$name} and made your composer.json provide {$result['package']}.");

        if ($this->option('no-composer')) {
            $this->line("Now run <info>composer update {$result['package']}</info> and delete bootstrap/cache/tessera-modules.php.");
        } else {
            $command = [PHP_OS_FAMILY === 'Windows' ? 'composer.bat' : 'composer', 'update', $result['package'], '--no-interaction'];
            $this->line('<fg=gray>$ '.implode(' ', $command).'</>');

            $run = Process::path(base_path())->timeout(900)->run($command, fn (string $type, string $output) => $this->output->write($output));

            if ($run->failed()) {
                $this->components->error('Composer failed. Your composer.json already provides the package; run the command above again when the problem is fixed.');

                return self::FAILURE;
            }
        }

        @unlink($this->laravel->bootstrapPath('cache/tessera-modules.php'));

        $this->components->info("The module [{$result['package']}] is yours now.");
        $this->line('<fg=gray>Run bun run build to rebuild the frontend. Updates of this package no longer reach your project: you maintain the code in app/Modules/'.$name.'.</>');

        return self::SUCCESS;
    }
}
