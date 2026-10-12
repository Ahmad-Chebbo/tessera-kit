<?php

use App\Modules\Expenses\Actions\SaveExpenseAction;
use App\Modules\Expenses\Actions\SaveRecurringExpenseAction;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Expenses\Models\RecurringExpense;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function actionExpenseActor(Tenant $tenant, ?Branch $branch, bool $seesEveryBranch): User
{
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch?->id]);

    if ($seesEveryBranch) {
        $user->givePermissionTo(Permission::findOrCreate('view all branches'));
    }

    return $user;
}

function actionReceipt(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF");
}

beforeEach(function () {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-10-14 10:00:00', 'UTC'));

    $this->tenant = Tenant::factory()->create(['timezone' => 'UTC']);
    $this->north = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->south = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->category = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->data = ['expense_category_id' => $this->category->id, 'amount' => 120, 'expense_date' => '2026-10-10', 'description' => 'Rent'];
});

describe('saving an expense', function () {
    it('records it for the actor\'s tenant with a receipt', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, true);

        $expense = app(SaveExpenseAction::class)->create($actor, [...$this->data, 'branch_id' => $this->south->id], actionReceipt());

        expect($expense->tenant_id)->toBe($this->tenant->id)
            ->and($expense->created_by)->toBe($actor->id)
            ->and($expense->branch_id)->toBe($this->south->id)
            ->and($expense->getFirstMedia('receipt'))->not->toBeNull();
    });

    it('books people held to a branch to their own, whatever was asked', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, false);

        $expense = app(SaveExpenseAction::class)->create($actor, [...$this->data, 'branch_id' => $this->south->id]);

        expect($expense->branch_id)->toBe($this->north->id);
    });

    it('refuses people held to a branch who belong to none', function () {
        app(SaveExpenseAction::class)->create(actionExpenseActor($this->tenant, null, false), $this->data);
    })->throws(AuthorizationException::class);

    it('updates the fields, clears the ones left out and swaps or removes the receipt', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, true);
        $expense = app(SaveExpenseAction::class)->create($actor, [...$this->data, 'spread_months' => 6, 'branch_id' => $this->north->id, 'employee_id' => $actor->id], actionReceipt());

        app(SaveExpenseAction::class)->update($actor, $expense, [...$this->data, 'amount' => 150, 'branch_id' => $this->south->id], null, true);

        $expense->refresh();

        expect((float) $expense->amount)->toBe(150.0)
            ->and($expense->spread_months)->toBeNull()
            ->and($expense->employee_id)->toBeNull()
            ->and($expense->branch_id)->toBe($this->south->id)
            ->and($expense->getFirstMedia('receipt'))->toBeNull();
    });
});

describe('saving a recurring expense', function () {
    beforeEach(function () {
        $this->recurring = [
            'expense_category_id' => $this->category->id, 'description' => 'Rent', 'amount' => 500, 'frequency' => 'monthly',
            'starts_on' => '2026-08-01', 'ends_on' => null,
        ];
    });

    it('creates it and records the entries already due', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, true);

        [$recurring, $created] = app(SaveRecurringExpenseAction::class)->create($actor, [...$this->recurring, 'branch_id' => $this->north->id]);

        expect($created)->toBe(3)
            ->and($recurring->runs_count)->toBe(3)
            ->and($recurring->is_active)->toBeTrue()
            ->and(Expense::where('recurring_expense_id', $recurring->id)->count())->toBe(3);
    });

    it('records nothing for a paused one', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, true);

        [$recurring, $created] = app(SaveRecurringExpenseAction::class)->create($actor, $this->recurring, false);

        expect($created)->toBe(0)->and($recurring->is_active)->toBeFalse()->and(Expense::count())->toBe(0);
    });

    it('updates the amount but leaves the frequency and start date alone', function () {
        $actor = actionExpenseActor($this->tenant, $this->north, true);
        [$recurring] = app(SaveRecurringExpenseAction::class)->create($actor, $this->recurring, false);

        app(SaveRecurringExpenseAction::class)->update($actor, $recurring, [...$this->recurring, 'amount' => 650, 'frequency' => 'weekly', 'starts_on' => '2026-01-01'], true);

        $recurring->refresh();

        expect((float) $recurring->amount)->toBe(650.0)
            ->and($recurring->frequency->value)->toBe('monthly')
            ->and($recurring->starts_on->toDateString())->toBe('2026-08-01')
            ->and($recurring->is_active)->toBeTrue()
            ->and(RecurringExpense::count())->toBe(1);
    });
});
