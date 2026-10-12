<?php

/**
 * Boots the application as a headless project (the environment decides which layers and modules are on), signs in
 * through a Sanctum token and prints what the API, the web paths and the domain services answer, as JSON. The database
 * must already be migrated. Used by the headless mode test, which runs it as a separate process with MODULES_LAYERS=api.
 */

use App\Modules\Attendance\Services\AttendanceService;
use App\Modules\Expenses\Actions\SaveExpenseAction;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Team\Models\User;
use App\Modules\Team\Services\PermissionSynchronizer;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Tessera\Engine\ModuleRegistry;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

app(PermissionSynchronizer::class)->sync();

$tenant = Tenant::factory()->create();
$branch = Branch::factory()->create(['tenant_id' => $tenant->id]);
$user = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id]);
$user->givePermissionTo(Permission::all());
$category = ExpenseCategory::factory()->create(['tenant_id' => $tenant->id]);
$token = $user->createToken('smoke')->plainTextToken;

$kernel = $app->make(HttpKernel::class);

$call = function (string $method, string $uri, array $body = [], bool $authenticated = true) use ($kernel, $app): array {
    $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];

    if ($authenticated) {
        $server['HTTP_AUTHORIZATION'] = 'Bearer '.$GLOBALS['token'];
    }

    $app['auth']->forgetGuards();
    $response = $kernel->handle(Request::create($uri, $method, [], [], [], $server, $body === [] ? null : json_encode($body)));

    return ['status' => $response->getStatusCode(), 'inertia' => $response->headers->has('X-Inertia'), 'body' => json_decode($response->getContent(), true)];
};

$GLOBALS['token'] = $token;

$expense = $call('POST', '/api/v1/expenses', ['expense_category_id' => $category->id, 'amount' => 40, 'expense_date' => now()->toDateString(), 'description' => 'Tape']);
$list = $call('GET', '/api/v1/expenses');
$checkIn = $call('POST', '/api/v1/attendance/check-in');

$domainExpense = app(SaveExpenseAction::class)->create($user, ['expense_category_id' => $category->id, 'amount' => 15, 'expense_date' => now()->toDateString(), 'description' => 'Stamps']);
$domainCheckOut = app(AttendanceService::class)->checkOut($user);

echo json_encode([
    'webLayer' => app(ModuleRegistry::class)->webEnabled(),
    'apiLayer' => app(ModuleRegistry::class)->apiEnabled(),
    'unauthenticated' => $call('GET', '/api/v1/expenses', [], false)['status'],
    'created' => $expense['status'],
    'listed' => $list['status'],
    'listedCount' => count($list['body']['data'] ?? []),
    'checkedIn' => $checkIn['status'],
    'inertiaHeader' => $expense['inertia'] || $list['inertia'] || $checkIn['inertia'],
    'webPaths' => [
        'root' => $call('GET', '/', [], false)['status'],
        'login' => $call('GET', '/login', [], false)['status'],
        'dashboard' => $call('GET', '/dashboard')['status'],
        'expensesPage' => $call('GET', '/expenses')['status'],
    ],
    'domainExpense' => $domainExpense->description,
    'domainCheckOut' => $domainCheckOut->check_out_at !== null,
]);
