<?php

namespace App\Modules\Subscription\Console;

use App\Modules\Subscription\Actions\ConvertToSaasAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

class SaasInstallCommand extends Command
{
    protected $signature = 'saas:install
        {--admin-email= : Create the platform admin with this email, or give the Admin role to the existing user who has it}
        {--admin-name=Admin : The name of the platform admin when one is created}
        {--admin-password= : The password of the platform admin when one is created; a random one is made and shown otherwise}';

    protected $description = 'Turn a single-business project into one that serves many tenants: roles, plan and the platform admin';

    public function handle(ConvertToSaasAction $convert): int
    {
        try {
            $report = $convert->execute($this->option('admin-email') ?: null, (string) $this->option('admin-name'), $this->option('admin-password') ?: null);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info($report['permissions_created'].' permissions created. Plan modules: '.implode(', ', $report['plan_modules']).'.');

        foreach ($report['tenants'] as $tenant) {
            $this->components->twoColumnDetail($tenant['name'], $tenant['roles_moved'].' users moved to the tenant\'s own roles'.($tenant['subscribed'] ? ', subscribed to the plan' : ''));
        }

        if ($report['admin'] === null) {
            $this->line('No platform admin was created. Create one with <info>php artisan saas:install --admin-email=you@example.com</info>.');

            return self::SUCCESS;
        }

        $this->components->info(($report['admin']['created'] ? 'Created' : 'Gave the Admin role to').' the platform admin '.$report['admin']['email'].'.');

        if ($report['admin']['password'] !== null) {
            $this->line('Password: <info>'.$report['admin']['password'].'</info> (shown once; change it after signing in)');
        }

        return self::SUCCESS;
    }
}
