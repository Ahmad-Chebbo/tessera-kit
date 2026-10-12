<?php

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function commandContains(string $needle): Closure
{
    return fn (PendingProcess $process, ?ProcessResult $result = null): bool => str_contains(implode(' ', (array) $process->command), $needle);
}

it('requires the package, migrates and syncs permissions, in that order', function () {
    Process::fake();
    $project = sys_get_temp_dir().'/tessera-add-'.uniqid();
    mkdir($project);
    file_put_contents($project.'/composer.json', '{}');
    app()->setBasePath($project);
    config(['tessera.repository' => 'https://example.test/packages', 'tessera.package_prefix' => 'tessera-']);

    $this->artisan('module:add', ['key' => 'invoices'])->assertSuccessful();

    Process::assertRanInOrder([
        commandContains('config repositories.tessera composer https://example.test/packages'),
        commandContains('require ahmad-chebbo/tessera-invoices --no-interaction'),
        commandContains('artisan migrate'),
        commandContains('artisan modules:sync'),
    ]);
});

it('stops at the first command that fails', function () {
    Process::fake(['*' => Process::result(exitCode: 1)]);

    $this->artisan('module:add', ['key' => 'invoices', '--constraint' => '^1.2'])->assertFailed();

    Process::assertRanTimes(commandContains(''), 1);
});

it('does not add a repository the project already has', function () {
    Process::fake();
    $project = sys_get_temp_dir().'/tessera-add-'.uniqid();
    mkdir($project);
    file_put_contents($project.'/composer.json', json_encode(['repositories' => [['type' => 'composer', 'url' => config('tessera.repository')]]]));
    app()->setBasePath($project);

    $this->artisan('module:add', ['key' => 'expenses'])->assertSuccessful();

    Process::assertNotRan(commandContains('config repositories'));
    Process::assertRan(commandContains('require ahmad-chebbo/tessera-expenses'));
});
