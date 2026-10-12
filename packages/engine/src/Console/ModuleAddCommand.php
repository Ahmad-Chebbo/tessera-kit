<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Process;

class ModuleAddCommand extends Command
{
    protected $signature = 'module:add {key : The key of the module, for example expenses}
        {--constraint= : The version to require, for example ^1.2}
        {--build : Also build the frontend}';

    protected $description = 'Install a module from its package: require it, run its migrations and give the roles its permissions';

    public function handle(): int
    {
        $key = (string) $this->argument('key');
        $vendor = (string) config('tessera.vendor');
        $package = $vendor.'/'.config('tessera.package_prefix').$key;

        if (! $this->hasRepository()) {
            if (! $this->runProcess([$this->composer(), 'config', 'repositories.tessera', 'composer', (string) config('tessera.repository')])) {
                return self::FAILURE;
            }
        }

        $require = $package.($this->option('constraint') ? ':'.$this->option('constraint') : '');

        if (! $this->runProcess([$this->composer(), 'require', $require, '--no-interaction'])) {
            return self::FAILURE;
        }

        if (! $this->runProcess([PHP_BINARY, 'artisan', 'migrate', '--no-interaction'])) {
            return self::FAILURE;
        }

        if (array_key_exists('modules:sync', Artisan::all()) && ! $this->runProcess([PHP_BINARY, 'artisan', 'modules:sync', '--no-interaction'])) {
            return self::FAILURE;
        }

        if ($this->option('build')) {
            if (! $this->runProcess(['bun', 'run', 'build'])) {
                return self::FAILURE;
            }
        } else {
            $this->components->info('Build the frontend to show its pages: bun run build');
        }

        $this->components->info("The module [{$key}] is installed.");

        return self::SUCCESS;
    }

    private function composer(): string
    {
        return PHP_OS_FAMILY === 'Windows' ? 'composer.bat' : 'composer';
    }

    /**
     * Whether composer.json already reaches the packages: through the Tessera repository or a path repository that
     * covers the packages folder of a monorepo.
     */
    private function hasRepository(): bool
    {
        $composer = json_decode((string) @file_get_contents(base_path('composer.json')), true) ?? [];

        foreach ($composer['repositories'] ?? [] as $repository) {
            $url = (string) ($repository['url'] ?? '');

            if ($url === config('tessera.repository') || (($repository['type'] ?? '') === 'path' && str_contains($url, '*'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command): bool
    {
        $this->line('<fg=gray>$ '.implode(' ', $command).'</>');

        $result = Process::path(base_path())->timeout(900)->run($command, fn (string $type, string $output) => $this->output->write($output));

        if ($result->failed()) {
            $this->components->error('That command failed, so the module was not fully installed.');

            return false;
        }

        return true;
    }
}
