<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Tessera\Engine\ModuleChecker;

class ModuleCheckCommand extends Command
{
    protected $signature = 'module:check {--strict : Fail on warnings too}';

    protected $description = 'Check that the modules can be moved between projects: manifests, imports, permissions, translations and more';

    public function handle(ModuleChecker $checker): int
    {
        $issues = $checker->run();
        $errors = count(array_filter($issues, fn (array $issue): bool => $issue['level'] === ModuleChecker::ERROR));
        $warnings = count($issues) - $errors;

        if ($issues === []) {
            $this->components->info('All modules look portable.');

            return self::SUCCESS;
        }

        $this->table(['Level', 'Module', 'Problem'], array_map(fn (array $issue): array => [$issue['level'], $issue['module'], $issue['message']], $issues));
        $this->line("{$errors} errors, {$warnings} warnings.");

        return $errors > 0 || ($warnings > 0 && $this->option('strict')) ? self::FAILURE : self::SUCCESS;
    }
}
