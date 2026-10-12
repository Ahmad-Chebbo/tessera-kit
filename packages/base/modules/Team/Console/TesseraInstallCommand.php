<?php

namespace App\Modules\Team\Console;

use App\Modules\Team\Actions\InstallFirstAccountAction;
use Illuminate\Console\Command;
use InvalidArgumentException;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class TesseraInstallCommand extends Command
{
    protected $signature = 'tessera:install
        {--name= : The name of the first account}
        {--email= : The email of the first account}
        {--password= : The password of the first account; asked for, or made and shown once when not interactive}';

    protected $description = 'Create the permissions and roles, then the first account: the Owner, or the platform Admin when the project serves many tenants';

    public function handle(InstallFirstAccountAction $install): int
    {
        $interactive = $this->input->isInteractive();

        $name = $this->option('name') ?: ($interactive ? text('Name', default: 'Admin', required: true) : 'Admin');
        $email = $this->option('email') ?: ($interactive ? text('Email', required: true, validate: fn (string $value): ?string => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Enter a valid email address.') : null);

        if (! $email) {
            $this->components->error('Give the email of the first account with --email.');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: ($interactive ? password('Password (leave empty for a random one)', hint: 'At least 8 characters.') : null);

        try {
            $result = $install->execute($email, $name, $password ?: null);
        } catch (InvalidArgumentException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(($result['created'] ? 'Created' : 'Gave the '.$result['role'].' role to').' '.$result['email'].($result['created'] ? ' with the '.$result['role'].' role' : '').'.');

        if ($result['created'] && ! $this->option('password') && ! $password) {
            $this->line('Password: <info>'.$result['password'].'</info> (shown once; change it after signing in)');
        }

        return self::SUCCESS;
    }
}
