<?php

namespace App\Modules\Expenses\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Http\Requests\ExpenseFilterRequest;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use App\Modules\Expenses\Services\ExpenseLedger;
use App\Modules\Expenses\Services\ProfitStatement;
use App\Modules\Tenant\Services\BranchAccess;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseReportController extends Controller
{
    public function __construct(private readonly ExpenseLedger $ledger, private readonly ProfitStatement $statement, private readonly BranchAccess $access) {}

    public function report(ExpenseFilterRequest $request, ExpenseCategoryDefaults $defaults): Response
    {
        $tenantId = $request->user()->tenant_id;
        $defaults->ensure($tenantId);

        [$from, $to] = $request->dateRange();
        $filters = $request->filters();
        $breakdown = $this->ledger->breakdown($tenantId, $from, $to, $filters);

        $months = $this->ledger->monthsBetween($from, $to);
        $byMonth = collect($breakdown['by_month'])->keyBy('month');

        return Inertia::render('Expenses/Report', [
            'report' => [
                ...$breakdown,
                'by_month' => $months->map(fn (string $month): array => $byMonth[$month] ?? ['month' => $month, 'total' => 0.0, 'included' => 0.0])->values()->all(),
                'average' => $breakdown['count'] > 0 ? round($breakdown['total'] / $breakdown['count'], 2) : 0.0,
            ],
            'filters' => [...$filters, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'presets' => $request->presets(),
            'lookups' => [
                'categories' => ExpenseCategory::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
                'branches' => $this->access->branches($request->user()),
                'types' => ExpenseType::values(),
            ],
        ]);
    }

    public function statement(ExpenseFilterRequest $request): Response
    {
        $tenantId = $request->user()->tenant_id;
        [$from, $to] = $request->dateRange();
        $branchId = $request->filters()['branch_id'] ?? null;

        return Inertia::render('Expenses/Statement', [
            'statement' => $this->statement->forPeriod($tenantId, $from, $to, $branchId),
            'filters' => ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'branch_id' => $branchId],
            'presets' => $request->presets(),
            'lookups' => ['branches' => $this->access->branches($request->user())],
            'canViewProfit' => Gate::allows('view reports'),
        ]);
    }
}
