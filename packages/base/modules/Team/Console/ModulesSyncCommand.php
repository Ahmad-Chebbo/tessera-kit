<?php

namespace App\Modules\Team\Console;

use App\Modules\Team\Services\PermissionSynchronizer;
use Illuminate\Console\Command;

class ModulesSyncCommand extends Command
{
    protected $signature = 'modules:sync';

    protected $description = 'Create the permissions and default role permissions the enabled modules declare';

    public function handle(PermissionSynchronizer $synchronizer): int
    {
        $result = $synchronizer->sync();

        $this->info(count($result['created']).' permissions created, '.count($result['roles']).' template roles updated.');

        return self::SUCCESS;
    }
}
