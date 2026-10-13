<?php

use Illuminate\Support\Facades\Process;

/**
 * Asks a freshly booted application, started with the given modules, which permissions and menu routes it declares.
 *
 * @return array{permissions: list<string>, routes: list<string>}
 */
function declaredWith(string $enabled): array
{
    $code = 'echo json_encode(["permissions" => app(Tessera\Engine\ModuleRegistry::class)->permissions(), "routes" => array_map(fn ($item) => $item->route, app(Tessera\Engine\ModuleRegistry::class)->menuItems())]);';
    $run = Process::path(base_path())->env(['MODULES_ENABLED' => $enabled, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'])
        ->run([PHP_BINARY, 'artisan', 'tinker', '--execute', $code]);
    $output = trim($run->output());

    expect($run->successful() && str_contains($output, '{'))->toBeTrue($run->errorOutput().$output);

    return json_decode(substr($output, strpos($output, '{')), true);
}

it('hides stock locations and exchange rates from a project without stock or a till', function () {
    $declared = declaredWith('shifts,attendance');

    expect($declared['permissions'])->not->toContain('manage locations')
        ->and($declared['permissions'])->toContain('manage branches', 'manage settings')
        ->and($declared['routes'])->not->toContain('locations.index', 'exchange-rates.index')
        ->and($declared['routes'])->toContain('branches.index', 'settings.index');
});

it('shows them once inventory is installed', function () {
    $declared = declaredWith('catalog,inventory');

    expect($declared['permissions'])->toContain('manage locations')
        ->and($declared['routes'])->toContain('locations.index', 'exchange-rates.index');
});
