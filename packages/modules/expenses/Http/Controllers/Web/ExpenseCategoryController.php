<?php

namespace App\Modules\Expenses\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Http\Requests\StoreExpenseCategoryRequest;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, ExpenseCategoryDefaults $defaults): Response
    {
        $tenantId = $request->user()->tenant_id;
        $defaults->ensure($tenantId);

        return Inertia::render('Expenses/Categories', [
            'categories' => ExpenseCategory::query()->where('tenant_id', $tenantId)->withCount('expenses')->orderBy('name')->get()
                ->map(fn (ExpenseCategory $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'type' => $category->type->value,
                    'affects_profit' => $category->affects_profit,
                    'is_active' => $category->is_active,
                    'expenses_count' => $category->expenses_count,
                ]),
            'types' => collect(ExpenseType::cases())->map(fn (ExpenseType $type): array => ['value' => $type->value, 'affects_profit' => $type->affectsProfitByDefault()]),
            'can' => ['edit' => $request->user()->can('edit expenses')],
        ]);
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        ExpenseCategory::create([...$request->attributesToSave(), 'tenant_id' => $request->user()->tenant_id]);

        return back()->with('success', 'Category created.');
    }

    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $category): RedirectResponse
    {
        $this->ensureOwned($request, $category);
        $category->update($request->attributesToSave());

        return back()->with('success', 'Category updated.');
    }

    public function destroy(Request $request, ExpenseCategory $category): RedirectResponse
    {
        abort_unless($request->user()->can('edit expenses'), 403);
        $this->ensureOwned($request, $category);

        if ($category->expenses()->exists()) {
            return back()->with('error', 'This category has expenses. Deactivate it instead.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function ensureOwned(Request $request, ExpenseCategory $category): void
    {
        abort_unless($category->tenant_id === $request->user()->tenant_id, 403);
    }
}
