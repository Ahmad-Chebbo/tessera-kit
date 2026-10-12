<?php

namespace App\Modules\Expenses\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Http\Resources\ExpenseCategoryResource;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseCategoryController extends Controller
{
    public function index(Request $request, ExpenseCategoryDefaults $defaults): AnonymousResourceCollection
    {
        $tenantId = $request->user()->tenant_id;
        $defaults->ensure($tenantId);

        return ExpenseCategoryResource::collection(ExpenseCategory::query()->where('tenant_id', $tenantId)->orderBy('name')->get());
    }
}
