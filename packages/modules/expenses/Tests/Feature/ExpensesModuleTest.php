<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Expenses\Enums\ExpenseType;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\RecurringExpense;
use App\Modules\Expenses\Services\ExpenseLedger;
use App\Modules\Expenses\Services\ProfitStatement;
use App\Modules\Expenses\Services\RecurringExpenseRunner;
use App\Modules\Inventory\Models\Purchase;
use App\Modules\Inventory\Models\Supplier;
use App\Modules\Sales\Models\Order;
use App\Modules\Subscription\Enums\Module;
use App\Modules\Subscription\Models\Plan;
use App\Modules\Subscription\Models\Subscription;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function expenseCategory(string $name, ExpenseType $type = ExpenseType::Operating, ?bool $affects = null): ExpenseCategory
{
    return ExpenseCategory::factory()->ofType($type)->create([
        'tenant_id' => test()->tenant->id,
        'name' => $name,
        ...($affects !== null ? ['affects_profit' => $affects] : []),
    ]);
}

function recordExpense(ExpenseCategory $category, float $amount, string $date, array $extra = []): Expense
{
    return Expense::factory()->create([
        'created_by' => test()->manager->id,
        'tenant_id' => test()->tenant->id,
        'expense_category_id' => $category->id,
        'amount' => $amount,
        'expense_date' => $date,
        ...$extra,
    ]);
}

function expensePayload(array $overrides = []): array
{
    return [
        'expense_category_id' => test()->rent->id,
        'amount' => 450,
        'expense_date' => '2026-10-10',
        'description' => 'October rent',
        ...$overrides,
    ];
}

function ledger(string $from, string $to, array $filters = []): array
{
    return app(ExpenseLedger::class)->breakdown(test()->tenant->id, CarbonImmutable::parse($from), CarbonImmutable::parse($to), $filters);
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00', 'UTC'));

    foreach (['view expenses', 'create expenses', 'edit expenses', 'delete expenses', 'view reports', 'view all branches'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Main']);
    $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id])
        ->givePermissionTo(['view expenses', 'create expenses', 'edit expenses', 'delete expenses', 'view reports', 'view all branches']);

    $this->other = Tenant::factory()->create();
    $this->otherManager = User::factory()->create(['tenant_id' => $this->other->id])->givePermissionTo(['view expenses', 'create expenses', 'edit expenses', 'delete expenses']);

    $this->rent = expenseCategory('Rent');
    $this->salaries = expenseCategory('Salaries', ExpenseType::Salaries);
    $this->assets = expenseCategory('Equipment', ExpenseType::Assets);
    $this->stock = expenseCategory('Stock', ExpenseType::Purchases);
});

describe('categories', function () {
    it('starts a tenant with sensible default categories the first time it opens expenses', function () {
        $fresh = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $fresh->id])->givePermissionTo('view expenses');

        $this->actingAs($user)->get(route('expense-categories.index'))->assertOk();

        $categories = ExpenseCategory::where('tenant_id', $fresh->id)->get()->keyBy('name');
        expect($categories)->toHaveCount(9)
            ->and($categories['Salaries']->type)->toBe(ExpenseType::Salaries)->and($categories['Salaries']->affects_profit)->toBeTrue()
            ->and($categories['Stock purchases']->affects_profit)->toBeFalse()
            ->and($categories['Equipment & assets']->affects_profit)->toBeFalse()
            ->and($categories['Taxes & fees']->affects_profit)->toBeTrue();

        $this->actingAs($user)->get(route('expense-categories.index'));
        expect(ExpenseCategory::where('tenant_id', $fresh->id)->count())->toBe(9);
    });

    it('creates and renames categories with a type and a profit switch', function () {
        $this->actingAs($this->manager)->post(route('expense-categories.store'), ['name' => 'Insurance', 'type' => 'operating', 'affects_profit' => false, 'is_active' => true])->assertSessionHasNoErrors();

        $category = ExpenseCategory::where('name', 'Insurance')->firstOrFail();
        expect($category->affects_profit)->toBeFalse()->and($category->tenant_id)->toBe($this->tenant->id);

        $this->actingAs($this->manager)->put(route('expense-categories.update', $category), ['name' => 'Insurance fees', 'type' => 'tax', 'affects_profit' => true, 'is_active' => true])->assertSessionHasNoErrors();
        expect($category->fresh()->name)->toBe('Insurance fees')->and($category->fresh()->type)->toBe(ExpenseType::Tax)->and($category->fresh()->affects_profit)->toBeTrue();
    });

    it('follows the type default when the switch is not sent', function () {
        $this->actingAs($this->manager)->post(route('expense-categories.store'), ['name' => 'Machines', 'type' => 'assets'])->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('expense-categories.store'), ['name' => 'Cleaning', 'type' => 'operating'])->assertSessionHasNoErrors();

        expect(ExpenseCategory::where('name', 'Machines')->first()->affects_profit)->toBeFalse()
            ->and(ExpenseCategory::where('name', 'Cleaning')->first()->affects_profit)->toBeTrue();
    });

    it('keeps names unique inside a tenant only and validates the type', function () {
        $this->actingAs($this->manager)->post(route('expense-categories.store'), ['name' => 'Rent', 'type' => 'operating'])->assertSessionHasErrors('name');
        $this->actingAs($this->manager)->post(route('expense-categories.store'), ['name' => 'Bad', 'type' => 'magic'])->assertSessionHasErrors('type');
        $this->flushSession();
        $this->actingAs($this->otherManager)->post(route('expense-categories.store'), ['name' => 'Rent', 'type' => 'operating'])->assertSessionHasNoErrors();
    });

    it('refuses to delete a category that has expenses', function () {
        recordExpense($this->rent, 10, '2026-10-01');

        $this->actingAs($this->manager)->delete(route('expense-categories.destroy', $this->rent))->assertSessionHas('error');
        expect(ExpenseCategory::find($this->rent->id))->not->toBeNull();

        $this->actingAs($this->manager)->delete(route('expense-categories.destroy', $this->stock))->assertSessionHas('success');
        expect(ExpenseCategory::find($this->stock->id))->toBeNull();
    });

    it('keeps categories private to their tenant', function () {
        $theirs = ExpenseCategory::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->manager)->put(route('expense-categories.update', $theirs), ['name' => 'Hacked', 'type' => 'other'])->assertForbidden();
        $this->actingAs($this->manager)->delete(route('expense-categories.destroy', $theirs))->assertForbidden();
    });
});

describe('recording expenses', function () {
    it('records an expense for the tenant and the signed-in user', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['reference' => 'INV-77', 'branch_id' => $this->branch->id]))->assertRedirect(route('expenses.index'));

        $expense = Expense::firstOrFail();
        expect($expense->tenant_id)->toBe($this->tenant->id)->and($expense->created_by)->toBe($this->manager->id)
            ->and((float) $expense->amount)->toBe(450.0)->and($expense->reference)->toBe('INV-77')->and($expense->branch_id)->toBe($this->branch->id);
    });

    it('validates the fields', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['amount' => 0, 'description' => '', 'expense_date' => '10/10/2026']))
            ->assertSessionHasErrors(['amount', 'description', 'expense_date']);
    });

    it('only accepts active categories, branches and employees of the same tenant', function () {
        $foreignCategory = ExpenseCategory::factory()->create(['tenant_id' => $this->other->id]);
        $inactive = expenseCategory('Old');
        $inactive->update(['is_active' => false]);
        $foreignBranch = Branch::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $foreignCategory->id]))->assertSessionHasErrors('expense_category_id');
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $inactive->id]))->assertSessionHasErrors('expense_category_id');
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['branch_id' => $foreignBranch->id]))->assertSessionHasErrors('branch_id');
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['employee_id' => $this->otherManager->id]))->assertSessionHasErrors('employee_id');
    });

    it('can name the employee a salary is for', function () {
        $employee = User::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Layla']);

        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $this->salaries->id, 'employee_id' => $employee->id, 'description' => 'October salary']))->assertSessionHasNoErrors();

        expect(Expense::firstOrFail()->employee->name)->toBe('Layla');
    });

    it('allows spreading only asset expenses', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['spread_months' => 12]))->assertSessionHasErrors('spread_months');
        $this->flushSession();
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $this->assets->id, 'spread_months' => 12, 'amount' => 12000]))->assertSessionHasNoErrors();
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $this->assets->id, 'spread_months' => 1, 'description' => 'x']))->assertSessionHasErrors('spread_months');

        expect(Expense::firstOrFail()->spread_months)->toBe(12);
    });

    it('updates an expense and clears optional fields', function () {
        $expense = recordExpense($this->rent, 100, '2026-10-01', ['branch_id' => $this->branch->id, 'reference' => 'A']);

        $this->actingAs($this->manager)->put(route('expenses.update', $expense), expensePayload(['amount' => 120, 'branch_id' => null, 'reference' => null]))->assertRedirect(route('expenses.index'));

        $expense->refresh();
        expect((float) $expense->amount)->toBe(120.0)->and($expense->branch_id)->toBeNull()->and($expense->reference)->toBeNull();
    });

    it('deletes an expense', function () {
        $expense = recordExpense($this->rent, 100, '2026-10-01');

        $this->actingAs($this->manager)->delete(route('expenses.destroy', $expense))->assertRedirect();
        expect(Expense::find($expense->id))->toBeNull();
    });

    it('keeps expenses private to their tenant', function () {
        $theirs = Expense::factory()->create(['created_by' => $this->otherManager->id]);

        $this->actingAs($this->manager)->get(route('expenses.edit', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->put(route('expenses.update', $theirs), expensePayload())->assertForbidden();
        $this->actingAs($this->manager)->delete(route('expenses.destroy', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->get(route('expenses.receipt', $theirs))->assertForbidden();
    });

    it('checks each permission', function () {
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo('view expenses');
        $expense = recordExpense($this->rent, 10, '2026-10-01');

        $this->actingAs($viewer)->get(route('expenses.index'))->assertOk();
        $this->actingAs($viewer)->post(route('expenses.store'), expensePayload())->assertForbidden();
        $this->actingAs($viewer)->put(route('expenses.update', $expense), expensePayload())->assertForbidden();
        $this->actingAs($viewer)->delete(route('expenses.destroy', $expense))->assertForbidden();
        $this->actingAs($viewer)->post(route('expense-categories.store'), ['name' => 'X', 'type' => 'other'])->assertForbidden();

        $nobody = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->actingAs($nobody)->get(route('expenses.index'))->assertForbidden();
    });

    it('is off for tenants whose plan has no expenses module', function () {
        $plan = Plan::factory()->withModules([Module::Pos])->create();
        $this->tenant->subscriptions()->delete();
        Subscription::create(['tenant_id' => $this->tenant->id, 'plan_id' => $plan->id, 'status' => 'active', 'starts_at' => now()->subDay()]);

        $this->actingAs($this->manager)->get(route('expenses.index'))->assertForbidden();
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload())->assertForbidden();
    });
});

describe('receipts', function () {
    it('stores a receipt privately and serves it only to the tenant', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->image('receipt.jpg')]))->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();
        $media = $expense->getFirstMedia('receipt');
        expect($media)->not->toBeNull()->and($media->disk)->toBe('local');

        $this->actingAs($this->manager)->get(route('expenses.receipt', $expense))->assertOk()->assertHeader('content-type', 'image/jpeg');
        $this->actingAs($this->otherManager)->get(route('expenses.receipt', $expense))->assertForbidden();
        auth()->logout();
        $this->get(route('expenses.receipt', $expense))->assertRedirect();
    });

    it('accepts a PDF, replaces the receipt and can remove it', function () {
        $expense = recordExpense($this->rent, 10, '2026-10-01');

        $this->actingAs($this->manager)->put(route('expenses.update', $expense), expensePayload(['receipt' => UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")]))->assertSessionHasNoErrors();
        expect($expense->fresh()->getFirstMedia('receipt')->mime_type)->toBe('application/pdf');

        $this->actingAs($this->manager)->put(route('expenses.update', $expense), expensePayload(['receipt' => UploadedFile::fake()->image('new.png')]))->assertSessionHasNoErrors();
        expect($expense->fresh()->getMedia('receipt'))->toHaveCount(1)->and($expense->fresh()->getFirstMedia('receipt')->mime_type)->toBe('image/png');

        $this->actingAs($this->manager)->put(route('expenses.update', $expense), expensePayload(['remove_receipt' => true]))->assertSessionHasNoErrors();
        expect($expense->fresh()->getFirstMedia('receipt'))->toBeNull();
    });

    it('rejects other file types and files that are too big', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->create('virus.exe', 10)]))->assertSessionHasErrors('receipt');
        $this->flushSession();
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['receipt' => UploadedFile::fake()->createWithContent('big.pdf', "%PDF-1.4\n".str_repeat('x', 6 * 1024 * 1024))]))->assertSessionHasErrors('receipt');
        expect(Expense::count())->toBe(0);
    });

    it('returns 404 when there is no receipt', function () {
        $expense = recordExpense($this->rent, 10, '2026-10-01');

        $this->actingAs($this->manager)->get(route('expenses.receipt', $expense))->assertNotFound();
    });
});

describe('recording a received purchase as an expense', function () {
    beforeEach(function () {
        if (! moduleFolderInstalled('Inventory')) {
            $this->markTestSkipped('Inventory is not installed');
        }

        $this->supplier = Supplier::create(['tenant_id' => $this->tenant->id, 'name' => 'Acme']);
        $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id]);
        $this->purchase = Purchase::forceCreate(['tenant_id' => $this->tenant->id, 'supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'created_by' => $this->manager->id, 'reference_number' => 'PO-9', 'status' => 'received', 'subtotal' => 900, 'tax' => 0, 'total' => 900, 'received_at' => now()->subDay()]);
    });

    it('links a received purchase and lists only unlinked ones', function () {
        $draft = Purchase::forceCreate(['tenant_id' => $this->tenant->id, 'supplier_id' => $this->supplier->id, 'location_id' => $this->location->id, 'created_by' => $this->manager->id, 'reference_number' => 'PO-10', 'status' => 'pending', 'subtotal' => 5, 'tax' => 0, 'total' => 5]);

        $this->actingAs($this->manager)->get(route('expenses.create'))
            ->assertInertia(fn (Assert $page) => $page->component('Expenses/Edit')->has('purchases', 1)->where('purchases.0.id', $this->purchase->id)->where('purchases.0.total', 900));

        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['expense_category_id' => $this->stock->id, 'amount' => 900, 'purchase_id' => $this->purchase->id]))->assertSessionHasNoErrors();
        expect(Expense::firstOrFail()->purchase_id)->toBe($this->purchase->id);

        $this->actingAs($this->manager)->get(route('expenses.create'))->assertInertia(fn (Assert $page) => $page->has('purchases', 0));
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['purchase_id' => $draft->id]))->assertSessionHasErrors('purchase_id');
    });

    it('refuses to record the same purchase twice', function () {
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['purchase_id' => $this->purchase->id]))->assertSessionHasNoErrors();
        $this->flushSession();
        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['purchase_id' => $this->purchase->id, 'description' => 'again']))->assertSessionHasErrors('purchase_id');
    });

    it('does not offer another tenant\'s purchases', function () {
        $theirs = Purchase::forceCreate(['tenant_id' => $this->other->id, 'supplier_id' => Supplier::create(['tenant_id' => $this->other->id, 'name' => 'Theirs'])->id, 'location_id' => $this->location->id, 'created_by' => $this->otherManager->id, 'status' => 'received', 'subtotal' => 1, 'tax' => 0, 'total' => 1]);

        $this->actingAs($this->manager)->post(route('expenses.store'), expensePayload(['purchase_id' => $theirs->id]))->assertSessionHasErrors('purchase_id');
    });
})->skip(! moduleFolderInstalled('Inventory'), 'Inventory is not installed');

describe('the expense list', function () {
    it('lists the period with totals, the counted part and filters', function () {
        recordExpense($this->rent, 100, '2026-10-05');
        recordExpense($this->stock, 400, '2026-10-06', ['description' => 'Stock run']);
        recordExpense($this->rent, 999, '2026-08-01');

        $this->actingAs($this->manager)->get(route('expenses.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Expenses/Index')
                ->has('expenses.data', 2)
                ->where('summary.total', 500)
                ->where('summary.counted', 100)
                ->where('summary.count', 2)
                ->has('presets', 11));

        $this->actingAs($this->manager)->get(route('expenses.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'type' => 'purchases']))
            ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1)->where('expenses.data.0.description', 'Stock run'));

        $this->actingAs($this->manager)->get(route('expenses.index', ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'search' => 'stock']))
            ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1));
    });

    it('never lists another tenant\'s expenses and ignores garbage filters', function () {
        Expense::factory()->create(['created_by' => $this->otherManager->id]);
        recordExpense($this->rent, 1, '2026-10-10');

        $this->actingAs($this->manager)->get(route('expenses.index', ['category_id' => 'x', 'type' => 'nonsense', 'date_from' => 'bad']))
            ->assertInertia(fn (Assert $page) => $page->where('summary.count', 1));
    });
});

describe('the ledger', function () {
    it('totals by category, month and branch and splits what counts toward profit', function () {
        $second = Branch::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Second']);
        recordExpense($this->rent, 100, '2026-09-10', ['branch_id' => $this->branch->id]);
        recordExpense($this->rent, 50, '2026-10-10', ['branch_id' => $second->id]);
        recordExpense($this->stock, 400, '2026-10-12');

        $result = ledger('2026-09-01', '2026-10-31');

        expect($result['total'])->toBe(550.0)->and($result['included'])->toBe(150.0)->and($result['count'])->toBe(3)
            ->and(collect($result['by_category'])->firstWhere('name', 'Rent')['total'])->toBe(150.0)
            ->and(collect($result['by_month'])->pluck('total', 'month')->all())->toBe(['2026-09' => 100.0, '2026-10' => 450.0])
            ->and(collect($result['by_month'])->pluck('included', 'month')->all())->toBe(['2026-09' => 100.0, '2026-10' => 50.0])
            ->and(collect($result['by_branch'])->pluck('total', 'name')->all())->toBe(['' => 400.0, 'Main' => 100.0, 'Second' => 50.0]);
    });

    it('filters by category, branch, type, employee and text', function () {
        $employee = User::factory()->create(['tenant_id' => $this->tenant->id]);
        recordExpense($this->rent, 100, '2026-10-02', ['description' => 'Shop rent']);
        recordExpense($this->salaries, 700, '2026-10-03', ['employee_id' => $employee->id]);

        expect(ledger('2026-10-01', '2026-10-31', ['category_id' => $this->rent->id])['total'])->toBe(100.0)
            ->and(ledger('2026-10-01', '2026-10-31', ['type' => 'salaries'])['total'])->toBe(700.0)
            ->and(ledger('2026-10-01', '2026-10-31', ['employee_id' => $employee->id])['total'])->toBe(700.0)
            ->and(ledger('2026-10-01', '2026-10-31', ['search' => 'shop'])['total'])->toBe(100.0)
            ->and(ledger('2026-10-01', '2026-10-31', ['branch_id' => 999])['total'])->toBe(0.0);
    });

    it('only counts expenses inside the period', function () {
        recordExpense($this->rent, 100, '2026-09-30');
        recordExpense($this->rent, 100, '2026-10-01');
        recordExpense($this->rent, 100, '2026-10-31');
        recordExpense($this->rent, 100, '2026-11-01');

        expect(ledger('2026-10-01', '2026-10-31')['total'])->toBe(200.0);
    });

    it('spreads an asset evenly per day over its months', function () {
        recordExpense($this->assets, 1200, '2026-01-01', ['spread_months' => 12]);

        expect(ledger('2026-01-01', '2026-12-31')['total'])->toBe(1200.0)
            ->and(ledger('2026-01-01', '2026-01-31')['total'])->toBe(round(1200 / 365 * 31, 2))
            ->and(ledger('2025-01-01', '2025-12-31')['total'])->toBe(0.0)
            ->and(ledger('2027-01-01', '2027-12-31')['total'])->toBe(0.0)
            ->and(ledger('2026-01-01', '2026-12-31')['count'])->toBe(1)
            ->and(ledger('2027-01-01', '2027-12-31')['count'])->toBe(0);
    });

    it('splits a spread expense across the months of a longer period and always adds up to the full amount', function () {
        recordExpense($this->assets, 3000, '2026-01-15', ['spread_months' => 3]);

        $result = ledger('2026-01-01', '2026-06-30');

        expect($result['total'])->toBe(3000.0)
            ->and(collect($result['by_month'])->pluck('month')->all())->toBe(['2026-01', '2026-02', '2026-03', '2026-04'])
            ->and(array_sum(array_column($result['by_month'], 'total')))->toBe(3000.0);
    });

    it('does not count an asset toward profit by default but counts it when the category says so', function () {
        recordExpense($this->assets, 1200, '2026-01-01', ['spread_months' => 12]);
        expect(ledger('2026-01-01', '2026-12-31')['included'])->toBe(0.0);

        $this->assets->update(['affects_profit' => true]);
        expect(ledger('2026-01-01', '2026-12-31')['included'])->toBe(1200.0);
    });
});

describe('net profit', function () {
    beforeEach(function () {
        if (! moduleFolderInstalled('Sales') || ! moduleFolderInstalled('Customers')) {
            $this->markTestSkipped('Sales is not installed');
        }

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'cost_price' => 40, 'base_price' => 100, 'unlimited_quantity' => true]);
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Rana']);

        $sell = function (string $date, int $quantity, float $discount = 0, string $status = 'completed', ?Branch $branch = null) use ($product, $customer) {
            $order = Order::create([
                'tenant_id' => $this->tenant->id, 'branch_id' => ($branch ?? $this->branch)->id, 'order_number' => 'O-'.uniqid(),
                'subtotal' => 100 * $quantity, 'discount_amount' => $discount, 'total' => 100 * $quantity - $discount, 'status' => $status,
                'cashier_id' => $this->manager->id, 'customer_id' => $customer->id, 'payment_type' => 'cash',
            ]);
            $order->items()->create(['product_id' => $product->id, 'quantity' => $quantity, 'unit_price' => 100, 'cost_price' => 40, 'total' => 100 * $quantity]);
            $order->forceFill(['created_at' => CarbonImmutable::parse($date)])->save();
        };

        $this->second = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $sell('2026-09-10 12:00:00', 10, 50);
        $sell('2026-10-10 12:00:00', 5, 0);
        $sell('2026-10-11 12:00:00', 2, 0, 'voided');
        $sell('2026-10-12 12:00:00', 1, 0, 'completed', $this->second);
    });

    it('takes discounts, cost of goods and only the included expenses off the sales, month by month', function () {
        recordExpense($this->rent, 200, '2026-09-20');
        recordExpense($this->rent, 100, '2026-10-05');
        recordExpense($this->stock, 5000, '2026-10-06');

        $statement = app(ProfitStatement::class)->forPeriod($this->tenant->id, CarbonImmutable::parse('2026-09-01'), CarbonImmutable::parse('2026-10-31'));

        expect($statement['months'][0])->toMatchArray(['month' => '2026-09', 'revenue' => 1000.0, 'discounts' => 50.0, 'cogs' => 400.0, 'gross_profit' => 550.0, 'expenses' => 200.0, 'net_profit' => 350.0])
            ->and($statement['months'][1])->toMatchArray(['month' => '2026-10', 'revenue' => 600.0, 'discounts' => 0.0, 'cogs' => 240.0, 'gross_profit' => 360.0, 'expenses' => 100.0, 'net_profit' => 260.0])
            ->and($statement['totals'])->toMatchArray(['revenue' => 1600.0, 'discounts' => 50.0, 'cogs' => 640.0, 'gross_profit' => 910.0, 'expenses' => 300.0, 'net_profit' => 610.0, 'net_sales' => 1550.0]);
    });

    it('can be limited to one branch and shows empty months', function () {
        recordExpense($this->rent, 80, '2026-10-05', ['branch_id' => $this->second->id]);
        recordExpense($this->rent, 20, '2026-10-05', ['branch_id' => $this->branch->id]);

        $statement = app(ProfitStatement::class)->forPeriod($this->tenant->id, CarbonImmutable::parse('2026-08-01'), CarbonImmutable::parse('2026-10-31'), $this->second->id);

        expect(collect($statement['months'])->pluck('month')->all())->toBe(['2026-08', '2026-09', '2026-10'])
            ->and($statement['totals'])->toMatchArray(['revenue' => 100.0, 'cogs' => 40.0, 'expenses' => 80.0, 'net_profit' => -20.0]);
    });

    it('serves the statement and report pages', function () {
        recordExpense($this->rent, 100, '2026-10-05');

        $this->actingAs($this->manager)->get(route('expenses.statement', ['date_from' => '2026-09-01', 'date_to' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page->component('Expenses/Statement')->has('statement.months', 2)->where('statement.totals.expenses', 100));

        $this->actingAs($this->manager)->get(route('expenses.report', ['date_from' => '2026-09-01', 'date_to' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page->component('Expenses/Report')->where('report.total', 100)->has('report.by_month', 2)->where('report.average', 100));
    });

    it('adds net profit to the profit report when asked', function () {
        recordExpense($this->rent, 100, '2026-10-05');
        $range = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31'];

        $this->actingAs($this->manager)->get(route('reports.profit', $range))->assertInertia(fn (Assert $page) => $page->where('net', null)->where('canIncludeExpenses', true));

        $this->actingAs($this->manager)->get(route('reports.profit', [...$range, 'include_expenses' => 1]))
            ->assertInertia(fn (Assert $page) => $page->where('net.expenses', 100)->where('net.net_profit', 260)->where('filters.includeExpenses', true));
    })->skip(fn () => ! moduleInstalled('reports'), 'Reports is not installed');

    it('does not show net profit for a filtered product view or to people who cannot see expenses', function () {
        $range = ['date_from' => '2026-10-01', 'date_to' => '2026-10-31', 'include_expenses' => 1];

        $this->actingAs($this->manager)->get(route('reports.profit', [...$range, 'search' => 'x']))->assertInertia(fn (Assert $page) => $page->where('net', null)->where('filters.includeExpenses', true));

        $analyst = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo('view reports');
        $this->actingAs($analyst)->get(route('reports.profit', $range))->assertInertia(fn (Assert $page) => $page->where('net', null)->where('canIncludeExpenses', false)->where('filters.includeExpenses', false));
    })->skip(fn () => ! moduleInstalled('reports'), 'Reports is not installed');
})->skip(! moduleFolderInstalled('Sales') || ! moduleFolderInstalled('Customers'), 'Sales is not installed');

describe('recurring expenses', function () {
    function recurringPayload(array $overrides = []): array
    {
        return [
            'expense_category_id' => test()->rent->id, 'amount' => 500, 'description' => 'Shop rent', 'frequency' => 'monthly',
            'starts_on' => '2026-07-01', 'is_active' => true, ...$overrides,
        ];
    }

    it('records the entries that are already due when it is created', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload())->assertSessionHasNoErrors();

        $recurring = RecurringExpense::firstOrFail();
        expect(Expense::orderBy('expense_date')->pluck('expense_date')->map->toDateString()->all())->toBe(['2026-07-01', '2026-08-01', '2026-09-01', '2026-10-01'])
            ->and($recurring->runs_count)->toBe(4)->and($recurring->next_run_on->toDateString())->toBe('2026-11-01')->and($recurring->last_run_on->toDateString())->toBe('2026-10-01')
            ->and(Expense::first()->recurring_expense_id)->toBe($recurring->id)->and((float) Expense::first()->amount)->toBe(500.0);
    });

    it('creates the next entry when it falls due and never twice', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload());
        $runner = app(RecurringExpenseRunner::class);

        expect($runner->run(CarbonImmutable::parse('2026-10-14')))->toBe(0);

        expect($runner->run(CarbonImmutable::parse('2026-11-01')))->toBe(1)
            ->and($runner->run(CarbonImmutable::parse('2026-11-01')))->toBe(0)
            ->and(Expense::count())->toBe(5);
    });

    it('catches up on days the job missed', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-10-01']));

        expect(app(RecurringExpenseRunner::class)->run(CarbonImmutable::parse('2027-01-15')))->toBe(3)
            ->and(Expense::orderBy('expense_date')->pluck('expense_date')->map->toDateString()->all())->toBe(['2026-10-01', '2026-11-01', '2026-12-01', '2027-01-01']);
    });

    it('keeps month-end dates from drifting', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-01-31']));

        expect(Expense::orderBy('expense_date')->pluck('expense_date')->map->toDateString()->all())
            ->toBe(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31', '2026-09-30']);
    });

    it('repeats weekly and yearly', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['frequency' => 'weekly', 'starts_on' => '2026-09-30', 'description' => 'Cleaner']));
        expect(Expense::where('description', 'Cleaner')->pluck('expense_date')->map->toDateString()->all())->toBe(['2026-09-30', '2026-10-07', '2026-10-14']);

        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['frequency' => 'yearly', 'starts_on' => '2024-10-01', 'description' => 'Licence']));
        expect(Expense::where('description', 'Licence')->count())->toBe(3);
    });

    it('stops at its end date and switches itself off', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['ends_on' => '2026-08-15']));

        $recurring = RecurringExpense::firstOrFail();
        expect(Expense::count())->toBe(2)->and($recurring->is_active)->toBeFalse();
    });

    it('does nothing while paused and resumes where it left off', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-10-01']));
        $recurring = RecurringExpense::firstOrFail();

        $this->actingAs($this->manager)->post(route('recurring-expenses.toggle', $recurring))->assertRedirect();
        expect($recurring->fresh()->is_active)->toBeFalse()
            ->and(app(RecurringExpenseRunner::class)->run(CarbonImmutable::parse('2027-01-01')))->toBe(0);

        $this->actingAs($this->manager)->post(route('recurring-expenses.toggle', $recurring));
        expect(app(RecurringExpenseRunner::class)->run(CarbonImmutable::parse('2027-01-01')))->toBe(3);
    });

    it('does not record anything when created paused', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['is_active' => false]));

        expect(Expense::count())->toBe(0)->and(RecurringExpense::count())->toBe(1);
    });

    it('lets the amount change for future entries but not the schedule', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-10-01']));
        $recurring = RecurringExpense::firstOrFail();

        $this->actingAs($this->manager)->put(route('recurring-expenses.update', $recurring), recurringPayload(['amount' => 650, 'frequency' => 'weekly', 'starts_on' => '2020-01-01', 'description' => 'Rent (new)']))->assertSessionHasNoErrors();

        $recurring->refresh();
        expect((float) $recurring->amount)->toBe(650.0)->and($recurring->description)->toBe('Rent (new)')
            ->and($recurring->frequency->value)->toBe('monthly')->and($recurring->starts_on->toDateString())->toBe('2026-10-01');

        app(RecurringExpenseRunner::class)->run(CarbonImmutable::parse('2026-11-01'));
        expect((float) Expense::orderByDesc('expense_date')->first()->amount)->toBe(650.0)
            ->and((float) Expense::orderBy('expense_date')->first()->amount)->toBe(500.0);
    });

    it('keeps recorded entries when the recurring expense is deleted', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-10-01']));
        $recurring = RecurringExpense::firstOrFail();

        $this->actingAs($this->manager)->delete(route('recurring-expenses.destroy', $recurring))->assertRedirect();

        expect(RecurringExpense::count())->toBe(0)->and(Expense::count())->toBe(1)->and(Expense::first()->recurring_expense_id)->toBeNull();
    });

    it('validates the schedule and only spreads assets', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['frequency' => 'daily', 'amount' => 0]))->assertSessionHasErrors(['frequency', 'amount']);
        $this->flushSession();
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['ends_on' => '2026-01-01']))->assertSessionHasErrors('ends_on');
        $this->flushSession();
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['spread_months' => 6]))->assertSessionHasErrors('spread_months');
    });

    it('keeps recurring expenses private to their tenant and behind the permission', function () {
        $theirs = RecurringExpense::factory()->create(['created_by' => $this->otherManager->id]);
        $viewer = User::factory()->create(['tenant_id' => $this->tenant->id])->givePermissionTo('view expenses');

        $this->actingAs($this->manager)->put(route('recurring-expenses.update', $theirs), recurringPayload())->assertForbidden();
        $this->actingAs($this->manager)->post(route('recurring-expenses.toggle', $theirs))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('recurring-expenses.destroy', $theirs))->assertForbidden();
        $this->actingAs($viewer)->post(route('recurring-expenses.store'), recurringPayload())->assertForbidden();
        $this->actingAs($viewer)->get(route('recurring-expenses.index'))->assertOk();
    });

    it('lists the recurring expenses with their next entry', function () {
        $this->actingAs($this->manager)->post(route('recurring-expenses.store'), recurringPayload(['starts_on' => '2026-10-01']));

        $this->actingAs($this->manager)->get(route('recurring-expenses.index'))
            ->assertInertia(fn (Assert $page) => $page->component('Expenses/Recurring')->has('items', 1)->where('items.0.next_run_on', '2026-11-01')->where('items.0.runs_count', 1));
    });

    it('is scheduled to run every day', function () {
        $events = collect(app(Schedule::class)->events())->map(fn ($event) => $event->description);

        expect($events)->toContain('run-recurring-expenses');
    });
});
