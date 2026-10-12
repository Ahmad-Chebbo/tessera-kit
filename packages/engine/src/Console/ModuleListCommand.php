<?php

namespace Tessera\Engine\Console;

use Illuminate\Console\Command;
use Tessera\Engine\ModuleRegistry;

class ModuleListCommand extends Command
{
    protected $signature = 'module:list';

    protected $description = 'List the installed modules and whether they are enabled';

    public function handle(ModuleRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->all() as $key => $provider) {
            $manifest = $provider->manifest();

            $rows[] = [
                $key,
                $manifest->version,
                $registry->has($key) ? 'yes' : 'no',
                $manifest->core ? 'core' : 'feature',
                implode(', ', $manifest->requires) ?: '-',
            ];
        }

        $this->table(['Key', 'Version', 'Enabled', 'Type', 'Requires'], $rows);
        $this->line('Layers: '.(implode(', ', $registry->layers()) ?: 'none').($registry->webEnabled() ? '' : ' (web layer off)'));

        return self::SUCCESS;
    }
}
