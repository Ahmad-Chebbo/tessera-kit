<?php

/**
 * Boots the application with the environment it was started with, signs in a user who holds every permission the
 * enabled modules declare and prints the HTTP status of each path in SMOKE_PATHS as JSON. The database must already
 * be migrated. Used by the module set tests, which run it as a separate process with MODULES_ENABLED set.
 */

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

app(PermissionSynchronizer::class)->sync();

$company = Company::factory()->create();
$branch = Branch::factory()->create(['company_id' => $company->id]);
$user = User::factory()->create(['company_id' => $company->id, 'branch_id' => $branch->id]);
$user->givePermissionTo(Permission::all());

$kernel = $app->make(HttpKernel::class);
$statuses = [];

foreach (json_decode((string) getenv('SMOKE_PATHS'), true) as $path) {
    Auth::guard('web')->setUser($user);

    $request = Request::create('/'.ltrim($path, '/'), 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html, application/xhtml+xml']);

    $response = $kernel->handle($request);
    $statuses[$path] = $response->getStatusCode();
}

echo json_encode($statuses);
