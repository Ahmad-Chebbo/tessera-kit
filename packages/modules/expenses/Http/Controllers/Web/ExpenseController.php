<?php

namespace App\Modules\Expenses\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Expenses\Actions\SaveExpenseAction;
use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Http\Requests\ExpenseFilterRequest;
use App\Modules\Expenses\Http\Requests\StoreExpenseRequest;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseAccess;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use App\Modules\Expenses\Services\ExpenseQuery;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tessera\Core\Contracts\PurchaseLookup;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseCategoryDefaults $defaults,
        private readonly BranchAccess $branches,
        private readonly ExpenseAccess $access,
        private readonly ExpenseQuery $expenses,
    ) {}

    public function index(ExpenseFilterRequest $request): Response
    {
        $companyId = $request->user()->company_id;
        $this->defaults->ensure($companyId);

        [$from, $to] = $request->dateRange();
        $filters = $request->filters();

        $query = fn (): Builder => $this->expenses->filtered($companyId, $from->toDateString(), $to->toDateString(), $filters);

        $expenses = $query()
            ->with(['category:id,name,type,affects_profit', 'branch:id,name', 'employee:id,name', 'creator:id,name'])
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Expense $expense): array => $this->payload($expense));

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'summary' => $this->expenses->summary($query()),
            'filters' => [...$filters, 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()],
            'presets' => $request->presets(),
            'lookups' => $this->lookups($request->user()),
            'can' => ['create' => $request->user()->can('create expenses'), 'edit' => $request->user()->can('edit expenses'), 'delete' => $request->user()->can('delete expenses')],
        ]);
    }

    public function create(Request $request): Response
    {
        $companyId = $request->user()->company_id;
        $this->defaults->ensure($companyId);

        return Inertia::render('Expenses/Edit', [
            'expense' => null,
            'lookups' => $this->lookups($request->user()),
            'purchases' => $this->linkablePurchases($companyId),
        ]);
    }

    public function store(StoreExpenseRequest $request, SaveExpenseAction $save): RedirectResponse
    {
        $save->create($request->user(), $request->safe()->except(['receipt', 'remove_receipt']), $request->file('receipt'));

        return redirect()->route('expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $this->ensureOwned($request, $expense);

        return Inertia::render('Expenses/Edit', [
            'expense' => [...$this->payload($expense), 'branch_id' => $expense->branch_id, 'employee_id' => $expense->employee_id, 'purchase_id' => $expense->purchase_id, 'notes' => $expense->notes],
            'lookups' => $this->lookups($request->user()),
            'purchases' => $this->linkablePurchases($request->user()->company_id, $expense->purchase_id),
        ]);
    }

    public function update(StoreExpenseRequest $request, Expense $expense, SaveExpenseAction $save): RedirectResponse
    {
        $this->ensureOwned($request, $expense);

        $save->update($request->user(), $expense, $request->safe()->except(['receipt', 'remove_receipt']), $request->file('receipt'), $request->boolean('remove_receipt'));

        return redirect()->route('expenses.index')->with('success', 'Expense updated.');
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('delete expenses'), 403);
        $this->ensureOwned($request, $expense);
        $expense->delete();

        return back()->with('success', 'Expense deleted.');
    }

    public function receipt(Request $request, Expense $expense): BinaryFileResponse
    {
        $this->ensureOwned($request, $expense);
        $media = $expense->getFirstMedia('receipt');

        abort_unless($media !== null && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type, 'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"']);
    }

    private function ensureOwned(Request $request, Expense $expense): void
    {
        $this->access->ensure($request->user(), $expense);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Expense $expense): array
    {
        return [
            'id' => $expense->id,
            'expense_category_id' => $expense->expense_category_id,
            'category' => $expense->category ? ['id' => $expense->category->id, 'name' => $expense->category->name, 'type' => $expense->category->type->value, 'affects_profit' => $expense->category->affects_profit] : null,
            'branch' => $expense->branch?->only(['id', 'name']),
            'employee' => $expense->employee?->only(['id', 'name']),
            'creator' => $expense->creator?->only(['id', 'name']),
            'amount' => (float) $expense->amount,
            'description' => $expense->description,
            'expense_date' => $expense->expense_date->toDateString(),
            'spread_months' => $expense->spread_months,
            'reference' => $expense->reference,
            'has_receipt' => $expense->getFirstMedia('receipt') !== null,
            'receipt_url' => $expense->getFirstMedia('receipt') ? route('expenses.receipt', $expense) : null,
            'from_recurring' => $expense->recurring_expense_id !== null,
            'purchase_id' => $expense->purchase_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lookups(User $user): array
    {
        $companyId = $user->company_id;

        return [
            'categories' => ExpenseCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'type', 'affects_profit', 'is_active'])
                ->map(fn (ExpenseCategory $category): array => ['id' => $category->id, 'name' => $category->name, 'type' => $category->type->value, 'affects_profit' => $category->affects_profit, 'is_active' => $category->is_active]),
            'branches' => $this->branches->branches($user),
            'employees' => $this->branches->scope(User::query()->where('company_id', $companyId), $user)->orderBy('name')->get(['id', 'name']),
            'types' => ExpenseType::values(),
        ];
    }

    /**
     * Received purchases that are not yet recorded as an expense (plus the one this expense already points at).
     *
     * @return list<array<string, mixed>>
     */
    private function linkablePurchases(int $companyId, ?int $current = null): array
    {
        return app(PurchaseLookup::class)->linkable($companyId, Expense::query()->whereNotNull('purchase_id')->pluck('purchase_id')->all(), $current);
    }
}
