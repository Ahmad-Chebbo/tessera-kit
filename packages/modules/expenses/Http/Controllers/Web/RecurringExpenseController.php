<?php

namespace App\Modules\Expenses\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Expenses\Actions\SaveRecurringExpenseAction;
use App\Modules\Expenses\Enums\RecurrenceFrequency;
use App\Modules\Expenses\Http\Requests\StoreRecurringExpenseRequest;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\RecurringExpense;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use App\Modules\Team\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecurringExpenseController extends Controller
{
    public function __construct(private readonly BranchAccess $access) {}

    public function index(Request $request, ExpenseCategoryDefaults $defaults): Response
    {
        $companyId = $request->user()->company_id;
        $defaults->ensure($companyId);

        return Inertia::render('Expenses/Recurring', [
            'items' => RecurringExpense::query()
                ->with(['category:id,name,type', 'branch:id,name', 'employee:id,name'])
                ->where('company_id', $companyId)
                ->tap(fn ($query) => $this->access->scope($query, $request->user()))
                ->orderByDesc('is_active')
                ->orderBy('next_run_on')
                ->get()
                ->map(fn (RecurringExpense $item): array => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'amount' => (float) $item->amount,
                    'frequency' => $item->frequency->value,
                    'starts_on' => $item->starts_on->toDateString(),
                    'ends_on' => $item->ends_on?->toDateString(),
                    'next_run_on' => $item->next_run_on->toDateString(),
                    'last_run_on' => $item->last_run_on?->toDateString(),
                    'runs_count' => $item->runs_count,
                    'is_active' => $item->is_active,
                    'spread_months' => $item->spread_months,
                    'expense_category_id' => $item->expense_category_id,
                    'category' => $item->category ? ['id' => $item->category->id, 'name' => $item->category->name, 'type' => $item->category->type->value] : null,
                    'branch_id' => $item->branch_id,
                    'branch' => $item->branch?->only(['id', 'name']),
                    'employee_id' => $item->employee_id,
                    'employee' => $item->employee?->only(['id', 'name']),
                    'notes' => $item->notes,
                ]),
            'lookups' => [
                'categories' => ExpenseCategory::query()->where('company_id', $companyId)->where('is_active', true)->orderBy('name')->get(['id', 'name', 'type']),
                'branches' => $this->access->branches($request->user()),
                'employees' => $this->access->scope(User::query()->where('company_id', $companyId), $request->user())->orderBy('name')->get(['id', 'name']),
                'frequencies' => RecurrenceFrequency::values(),
            ],
            'can' => ['edit' => $request->user()->can('edit expenses')],
        ]);
    }

    public function store(StoreRecurringExpenseRequest $request, SaveRecurringExpenseAction $save): RedirectResponse
    {
        [, $created] = $save->create($request->user(), $request->validated(), $request->boolean('is_active', true));

        return back()->with('success', $created > 0 ? "Recurring expense saved. {$created} entries were recorded so far." : 'Recurring expense saved.');
    }

    public function update(StoreRecurringExpenseRequest $request, RecurringExpense $recurring, SaveRecurringExpenseAction $save): RedirectResponse
    {
        $this->ensureOwned($request, $recurring);

        $save->update($request->user(), $recurring, $request->validated(), $request->boolean('is_active', true));

        return back()->with('success', 'Recurring expense updated.');
    }

    public function toggle(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        abort_unless($request->user()->can('edit expenses'), 403);
        $this->ensureOwned($request, $recurring);
        $recurring->update(['is_active' => ! $recurring->is_active]);

        return back()->with('success', $recurring->is_active ? 'Recurring expense resumed.' : 'Recurring expense paused.');
    }

    public function destroy(Request $request, RecurringExpense $recurring): RedirectResponse
    {
        abort_unless($request->user()->can('edit expenses'), 403);
        $this->ensureOwned($request, $recurring);
        $recurring->delete();

        return back()->with('success', 'Recurring expense deleted. Entries already recorded were kept.');
    }

    private function ensureOwned(Request $request, RecurringExpense $recurring): void
    {
        abort_unless($recurring->company_id === $request->user()->company_id, 403);
        $this->access->ensure($request->user(), $recurring->branch_id);
    }
}
