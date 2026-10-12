<?php

use Illuminate\Support\Facades\Process;

/**
 * @return array<string, mixed>
 */
function runHeadless(string $layers): array
{
    $database = tempnam(sys_get_temp_dir(), 'headless').'.sqlite';
    touch($database);

    $env = ['MODULES_LAYERS' => $layers, 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'];

    try {
        $migrate = Process::path(base_path())->env($env)->run([PHP_BINARY, 'artisan', 'migrate:fresh', '--force']);
        expect($migrate->successful())->toBeTrue($migrate->errorOutput().$migrate->output());

        $smoke = Process::path(base_path())->env($env)->run([PHP_BINARY, base_path('tests/Support/api-smoke.php')]);
        $output = trim($smoke->output());
        expect($smoke->successful() && str_contains($output, '{'))->toBeTrue($smoke->errorOutput().$output);

        return json_decode(substr($output, strpos($output, '{')), true);
    } finally {
        @unlink($database);
    }
}

it('runs as an API only project: tokens, JSON endpoints and domain services work, web pages do not exist', function () {
    $result = runHeadless('api');

    expect($result['webLayer'])->toBeFalse()
        ->and($result['apiLayer'])->toBeTrue()
        ->and($result['unauthenticated'])->toBe(401)
        ->and($result['created'])->toBe(201)
        ->and($result['listed'])->toBe(200)
        ->and($result['listedCount'])->toBe(1)
        ->and($result['checkedIn'])->toBe(201)
        ->and($result['inertiaHeader'])->toBeFalse()
        ->and($result['webPaths'])->toBe(['root' => 404, 'login' => 404, 'dashboard' => 404, 'expensesPage' => 404])
        ->and($result['domainExpense'])->toBe('Stamps')
        ->and($result['domainCheckOut'])->toBeTrue();
});

it('serves both layers when asked to, with the API behaving the same', function () {
    $result = runHeadless('web,api');

    expect($result['webLayer'])->toBeTrue()
        ->and($result['created'])->toBe(201)
        ->and($result['listedCount'])->toBe(1)
        ->and($result['webPaths']['login'])->toBe(200)
        ->and($result['webPaths']['root'])->toBe(302)
        ->and($result['domainExpense'])->toBe('Stamps');
});
