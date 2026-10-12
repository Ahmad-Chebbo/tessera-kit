<?php

use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

function apiExpenseUser(Tenant $tenant, ?Branch $branch, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch?->id])->givePermissionTo($permissions);
}

function apiExpense(array $attributes = []): Expense
{
    return Expense::factory()->create([
        'tenant_id' => test()->tenant->id,
        'expense_category_id' => test()->category->id,
        'branch_id' => test()->north->id,
        'created_by' => test()->boss->id,
        'amount' => 100,
        'expense_date' => '2026-10-10',
        ...$attributes,
    ]);
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00', 'UTC'));

    $this->tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $this->north = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->south = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Rent']);

    $all = ['view expenses', 'create expenses', 'edit expenses', 'delete expenses'];
    $this->boss = apiExpenseUser($this->tenant, $this->north, [...$all, 'view all branches']);
    $this->clerk = apiExpenseUser($this->tenant, $this->north, $all);
    $this->viewer = apiExpenseUser($this->tenant, $this->north, ['view expenses']);
});

describe('listing', function () {
    it('needs a token', function () {
        $this->getJson('/api/v1/expenses')->assertUnauthorized();
    });

    it('lists the period newest first with a summary', function () {
        apiExpense(['expense_date' => '2026-10-02', 'description' => 'Older']);
        apiExpense(['expense_date' => '2026-10-12', 'description' => 'Newer', 'amount' => 50]);
        apiExpense(['expense_date' => '2026-07-01', 'description' => 'Too old']);
        Sanctum::actingAs($this->boss);

        $this->getJson('/api/v1/expenses')
            ->assertOk()
            ->assertJsonPath('data.0.description', 'Newer')
            ->assertJsonPath('data.1.description', 'Older')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('summary.total', 150)
            ->assertJsonPath('summary.count', 2)
            ->assertJsonPath('data.0.category.name', 'Rent')
            ->assertJsonPath('meta.per_page', 20);
    });

    it('filters and pages', function () {
        apiExpense(['description' => 'Office rent']);
        apiExpense(['description' => 'Cleaning']);
        Sanctum::actingAs($this->boss);

        $this->getJson('/api/v1/expenses?search=rent')->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'Office rent');
        $this->getJson('/api/v1/expenses?per_page=1')->assertJsonCount(1, 'data')->assertJsonPath('meta.last_page', 2);
    });

    it('holds people without the all branches permission to their own branch', function () {
        apiExpense(['description' => 'North']);
        apiExpense(['description' => 'South', 'branch_id' => $this->south->id]);

        Sanctum::actingAs($this->clerk);
        $this->getJson('/api/v1/expenses?branch_id='.$this->south->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'North');

        Sanctum::actingAs($this->boss);
        $this->getJson('/api/v1/expenses')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/expenses?branch_id='.$this->south->id)->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'South');
    });

    it('needs the view permission', function () {
        Sanctum::actingAs(apiExpenseUser($this->tenant, $this->north, []));

        $this->getJson('/api/v1/expenses')->assertForbidden();
    });

    it('lists the categories', function () {
        Sanctum::actingAs($this->viewer);

        $this->getJson('/api/v1/expenses/categories')->assertOk()->assertJsonFragment(['name' => 'Rent', 'type' => 'operating']);
    });
});

describe('showing', function () {
    it('shows an expense of the tenant', function () {
        $expense = apiExpense(['description' => 'Rent']);
        Sanctum::actingAs($this->viewer);

        $this->getJson("/api/v1/expenses/{$expense->id}")->assertOk()->assertJsonPath('data.description', 'Rent')->assertJsonPath('data.branch.id', $this->north->id);
    });

    it('hides expenses of another tenant and of another branch', function () {
        $stranger = Expense::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);
        $southern = apiExpense(['branch_id' => $this->south->id]);
        Sanctum::actingAs($this->viewer);

        $this->getJson("/api/v1/expenses/{$stranger->id}")->assertForbidden();
        $this->getJson("/api/v1/expenses/{$southern->id}")->assertForbidden();
    });
});

describe('writing', function () {
    $payload = fn () => ['expense_category_id' => test()->category->id, 'amount' => 80, 'expense_date' => '2026-10-13', 'description' => 'Printer paper'];

    it('records an expense booked to the caller\'s branch', function () use ($payload) {
        Sanctum::actingAs($this->clerk);

        $this->postJson('/api/v1/expenses', [...$payload(), 'branch_id' => $this->south->id])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Printer paper')
            ->assertJsonPath('data.branch.id', $this->north->id);

        expect(Expense::count())->toBe(1);
    });

    it('validates the payload', function () {
        Sanctum::actingAs($this->clerk);

        $this->postJson('/api/v1/expenses', ['amount' => -5])->assertUnprocessable()->assertJsonValidationErrors(['expense_category_id', 'amount', 'expense_date', 'description']);
    });

    it('needs the create, edit and delete permissions', function () use ($payload) {
        $expense = apiExpense();
        Sanctum::actingAs($this->viewer);

        $this->postJson('/api/v1/expenses', $payload())->assertForbidden();
        $this->putJson("/api/v1/expenses/{$expense->id}", $payload())->assertForbidden();
        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertForbidden();
    });

    it('updates and deletes', function () use ($payload) {
        $expense = apiExpense();
        Sanctum::actingAs($this->clerk);

        $this->putJson("/api/v1/expenses/{$expense->id}", [...$payload(), 'amount' => 95])->assertOk()->assertJsonPath('data.amount', 95);
        expect((float) $expense->fresh()->amount)->toBe(95.0);

        $this->deleteJson("/api/v1/expenses/{$expense->id}")->assertNoContent();
        expect(Expense::count())->toBe(0);
    });

    it('will not touch an expense of another branch', function () use ($payload) {
        $southern = apiExpense(['branch_id' => $this->south->id]);
        Sanctum::actingAs($this->clerk);

        $this->putJson("/api/v1/expenses/{$southern->id}", $payload())->assertForbidden();
        $this->deleteJson("/api/v1/expenses/{$southern->id}")->assertForbidden();
    });

    it('stores a receipt and serves it back', function () use ($payload) {
        Sanctum::actingAs($this->clerk);
        $receipt = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");

        $created = $this->post('/api/v1/expenses', [...$payload(), 'receipt' => $receipt], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.has_receipt', true);

        $this->get($created->json('data.receipt_url'), ['Accept' => 'application/json'])->assertOk()->assertHeader('content-type', 'application/pdf');
    });
});
