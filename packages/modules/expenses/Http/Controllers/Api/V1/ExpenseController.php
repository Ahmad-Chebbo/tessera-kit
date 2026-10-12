<?php

namespace App\Modules\Expenses\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Expenses\Actions\SaveExpenseAction;
use App\Modules\Expenses\Http\Requests\ExpenseFilterRequest;
use App\Modules\Expenses\Http\Requests\StoreExpenseRequest;
use App\Modules\Expenses\Http\Resources\ExpenseResource;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Services\ExpenseAccess;
use App\Modules\Expenses\Services\ExpenseCategoryDefaults;
use App\Modules\Expenses\Services\ExpenseQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller
{
    private const WITH = ['category:id,name,type,affects_profit', 'branch:id,name', 'employee:id,name'];

    public function __construct(
        private readonly ExpenseAccess $access,
        private readonly ExpenseQuery $expenses,
        private readonly ExpenseCategoryDefaults $defaults,
    ) {}

    /**
     * The tenant's expenses inside a period (the last 30 days by default), newest first.
     */
    public function index(ExpenseFilterRequest $request): AnonymousResourceCollection
    {
        $tenantId = $request->user()->tenant_id;
        $this->defaults->ensure($tenantId);

        [$from, $to] = $request->dateRange();
        $query = $this->expenses->filtered($tenantId, $from->toDateString(), $to->toDateString(), $request->filters());

        return ExpenseResource::collection(
            (clone $query)->with(self::WITH)->orderByDesc('expense_date')->orderByDesc('id')->paginate(min(max($request->integer('per_page', 20), 1), 100))->withQueryString()
        )->additional(['summary' => $this->expenses->summary($query), 'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()]]);
    }

    public function show(Request $request, Expense $expense): ExpenseResource
    {
        $this->access->ensure($request->user(), $expense);

        return new ExpenseResource($expense->load(self::WITH));
    }

    public function store(StoreExpenseRequest $request, SaveExpenseAction $save): JsonResponse
    {
        $expense = $save->create($request->user(), $request->safe()->except(['receipt', 'remove_receipt']), $request->file('receipt'));

        return (new ExpenseResource($expense->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(StoreExpenseRequest $request, Expense $expense, SaveExpenseAction $save): ExpenseResource
    {
        $this->access->ensure($request->user(), $expense);

        $save->update($request->user(), $expense, $request->safe()->except(['receipt', 'remove_receipt']), $request->file('receipt'), $request->boolean('remove_receipt'));

        return new ExpenseResource($expense->refresh()->load(self::WITH));
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->access->ensure($request->user(), $expense);
        $expense->delete();

        return response()->json(null, 204);
    }

    public function receipt(Request $request, Expense $expense): BinaryFileResponse
    {
        $this->access->ensure($request->user(), $expense);
        $media = $expense->getFirstMedia('receipt');

        abort_unless($media !== null && is_file($media->getPath()), 404);

        return response()->file($media->getPath(), ['Content-Type' => $media->mime_type, 'Content-Disposition' => 'inline; filename="'.addslashes($media->file_name).'"']);
    }
}
