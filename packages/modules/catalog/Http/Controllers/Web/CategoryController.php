<?php

namespace App\Modules\Catalog\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Modules\Catalog\Http\Requests\StoreCategoryRequest;
use App\Modules\Catalog\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $categories = Category::query()
            ->where('tenant_id', $user->tenant_id)
            ->withCount('products')
            ->orderBy('name')
            ->paginate(20);

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(Request $request): Response
    {
        $user = $request->user();

        $parentCategories = Category::where('tenant_id', $user->tenant_id)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Categories/Create', [
            'parentCategories' => $parentCategories,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $validated['tenant_id'] = $user->tenant_id;
        Category::create($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(Request $request, Category $category): Response
    {
        $user = $request->user();

        if ($category->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        $parentCategories = Category::where('tenant_id', $user->tenant_id)
            ->whereNull('parent_id')
            ->where('id', '!=', $category->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Categories/Edit', [
            'category' => $category,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $user = $request->user();
        if ($category->tenant_id !== $user->tenant_id) {
            abort(403);
        }
        $validated = $request->validated();
        $category->update($validated);

        return redirect()->route('categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $user = $request->user();

        if ($category->tenant_id !== $user->tenant_id) {
            abort(403);
        }

        if ($category->products()->count() > 0) {
            return redirect()->route('categories.index')
                ->with('error', 'Cannot delete category with products.');
        }

        $category->delete();

        return redirect()->route('categories.index')
            ->with('success', 'Category deleted successfully.');
    }
}
