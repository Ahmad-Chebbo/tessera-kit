<?php

use App\Modules\Attendance\Models\AttendanceRecord;
use App\Modules\Catalog\Models\Product;
use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Company\Models\Location;
use App\Modules\Company\Services\BranchAccess;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\PosShift;
use App\Modules\Team\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function branchStaff(string $name, ?Branch $branch, array $permissions = []): User
{
    $user = User::factory()->create(['name' => $name, 'company_id' => test()->company->id, 'branch_id' => $branch?->id]);

    return $user->givePermissionTo($permissions);
}

function branchOrder(Branch $branch, string $number, User $cashier): Order
{
    return Order::create([
        'company_id' => test()->company->id, 'branch_id' => $branch->id, 'order_number' => $number,
        'subtotal' => 100, 'discount_amount' => 0, 'total' => 100, 'status' => 'completed',
        'cashier_id' => $cashier->id, 'payment_type' => 'cash',
    ]);
}

beforeEach(function () {
    foreach (['view orders', 'view reports', 'view inventory', 'view expenses', 'create expenses', 'edit expenses', 'view attendance', 'manage attendance', 'view shift reports', 'view users', 'view all branches', 'adjust inventory'] as $permission) {
        Permission::findOrCreate($permission);
    }

    $this->company = Company::factory()->create(['timezone' => 'UTC']);
    $this->north = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'North']);
    $this->south = Branch::factory()->create(['company_id' => $this->company->id, 'name' => 'South']);

    $this->all = ['view orders', 'view reports', 'view inventory', 'view expenses', 'create expenses', 'edit expenses', 'view attendance', 'manage attendance', 'view shift reports', 'view users', 'adjust inventory'];
    $this->boss = branchStaff('Boss', $this->north, [...$this->all, 'view all branches']);
    $this->northStaff = branchStaff('Nora', $this->north, $this->all);
    $this->southStaff = branchStaff('Sam', $this->south, $this->all);
    $this->homeless = branchStaff('Hana', null, $this->all);
});

describe('the rule', function () {
    it('lets only the permission holder see and pick every branch', function () {
        $access = app(BranchAccess::class);

        expect($access->canSeeAll($this->boss))->toBeTrue()
            ->and($access->effective($this->boss))->toBeNull()
            ->and($access->effective($this->boss, $this->south->id))->toBe($this->south->id)
            ->and($access->effective($this->northStaff, $this->south->id))->toBe($this->north->id)
            ->and($access->branches($this->northStaff)->pluck('id')->all())->toBe([$this->north->id])
            ->and($access->branches($this->boss))->toHaveCount(2)
            ->and($access->allows($this->northStaff, $this->south->id))->toBeFalse()
            ->and($access->allows($this->homeless, null))->toBeFalse();
    });

    it('ignores branches of another company', function () {
        $foreign = Branch::factory()->create();

        expect(app(BranchAccess::class)->effective($this->boss, $foreign->id))->toBeNull();
    });
});

describe('orders', function () {
    beforeEach(function () {
        branchOrder($this->north, 'N-1', $this->northStaff);
        branchOrder($this->south, 'S-1', $this->southStaff);
    });

    $numbers = fn (Assert $page) => collect($page->toArray()['props']['orders']['data'])->pluck('order_number')->all();

    it('limits restricted users to their own branch even when the url asks for another', function () {
        $this->actingAs($this->northStaff)->get(route('orders.index', ['branch_id' => $this->south->id]))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.order_number', 'N-1')->has('orders.data', 1));
    });

    it('lets the permission holder see all or one branch', function () {
        $this->actingAs($this->boss)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 2));
        $this->actingAs($this->boss)->get(route('orders.index', ['branch_id' => $this->south->id]))
            ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.order_number', 'S-1'));
    });

    it('forbids opening an order of another branch and shows nothing to people without a branch', function () {
        $this->actingAs($this->northStaff)->get(route('orders.show', Order::where('order_number', 'S-1')->first()))->assertForbidden();
        $this->actingAs($this->homeless)->get(route('orders.index'))->assertInertia(fn (Assert $page) => $page->has('orders.data', 0));
    });
});

describe('inventory', function () {
    beforeEach(function () {
        $this->northShelf = Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->north->id, 'name' => 'North shelf']);
        $this->southShelf = Location::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->south->id, 'name' => 'South shelf']);
    });

    it('only offers the locations of the user\'s branch', function () {
        $this->actingAs($this->northStaff)->get(route('inventory.index'))
            ->assertInertia(fn (Assert $page) => $page->has('locations', 1)->where('locations.0.name', 'North shelf'));
        $this->actingAs($this->boss)->get(route('inventory.index'))->assertInertia(fn (Assert $page) => $page->has('locations', 2));
        $this->actingAs($this->boss)->get(route('inventory.index', ['branch_id' => $this->south->id]))
            ->assertInertia(fn (Assert $page) => $page->has('locations', 1)->where('locations.0.name', 'South shelf'));
    });

    it('hides movements of other branches and blocks adjustments there', function () {
        $product = Product::factory()->create(['company_id' => $this->company->id]);
        foreach ([$this->northShelf, $this->southShelf] as $location) {
            StockMovement::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'location_id' => $location->id, 'type' => 'adjustment', 'quantity' => 5, 'created_by' => $this->boss->id]);
        }

        $this->actingAs($this->northStaff)->get(route('inventory.movements'))->assertInertia(fn (Assert $page) => $page->has('movements.data', 1));
        $this->actingAs($this->boss)->get(route('inventory.movements'))->assertInertia(fn (Assert $page) => $page->has('movements.data', 2));

        $this->actingAs($this->northStaff)->post(route('inventory.storeAdjustment'), ['product_id' => $product->id, 'location_id' => $this->southShelf->id, 'quantity' => 1])->assertForbidden();
    });
});

describe('expenses', function () {
    beforeEach(function () {
        $this->category = ExpenseCategory::factory()->create(['company_id' => $this->company->id, 'name' => 'Rent']);
        foreach ([$this->north, $this->south] as $branch) {
            Expense::factory()->create(['company_id' => $this->company->id, 'created_by' => $this->boss->id, 'expense_category_id' => $this->category->id, 'branch_id' => $branch->id, 'amount' => 100, 'expense_date' => now()->toDateString()]);
        }
    });

    it('lists only the own branch for restricted users and everything for the permission holder', function () {
        $this->actingAs($this->northStaff)->get(route('expenses.index', ['branch_id' => $this->south->id]))
            ->assertInertia(fn (Assert $page) => $page->has('expenses.data', 1)->where('expenses.data.0.branch.id', $this->north->id));
        $this->actingAs($this->boss)->get(route('expenses.index'))->assertInertia(fn (Assert $page) => $page->has('expenses.data', 2));
    });

    it('books new expenses to the user\'s own branch whatever is posted', function () {
        $this->actingAs($this->northStaff)->post(route('expenses.store'), [
            'expense_category_id' => $this->category->id, 'amount' => 20, 'expense_date' => now()->toDateString(),
            'description' => 'Tape', 'branch_id' => $this->south->id,
        ])->assertSessionHasNoErrors();

        expect(Expense::where('description', 'Tape')->value('branch_id'))->toBe($this->north->id);
    });

    it('forbids editing an expense of another branch', function () {
        $theirs = Expense::where('branch_id', $this->south->id)->first();

        $this->actingAs($this->northStaff)->get(route('expenses.edit', $theirs))->assertForbidden();
        $this->actingAs($this->northStaff)->delete(route('expenses.destroy', $theirs))->assertForbidden();
    });
});

describe('attendance, shifts and people', function () {
    it('limits attendance records to the employees of the user\'s branch', function () {
        foreach ([$this->northStaff, $this->southStaff] as $employee) {
            AttendanceRecord::create(['company_id' => $this->company->id, 'user_id' => $employee->id, 'work_date' => now()->toDateString(), 'check_in_at' => now(), 'source' => 'manual']);
        }

        $this->actingAs($this->northStaff)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
        $this->actingAs($this->boss)->get(route('attendance.index'))->assertInertia(fn (Assert $page) => $page->has('records.data', 2));
        $this->actingAs($this->boss)->get(route('attendance.index', ['branch_id' => $this->south->id]))->assertInertia(fn (Assert $page) => $page->has('records.data', 1));
    });

    it('limits POS shifts and the employee list', function () {
        foreach ([[$this->north, $this->northStaff], [$this->south, $this->southStaff]] as [$branch, $employee]) {
            PosShift::create(['company_id' => $this->company->id, 'branch_id' => $branch->id, 'user_id' => $employee->id, 'open_user_id' => $employee->id, 'status' => PosShift::OPEN, 'opened_at' => now(), 'opening_float' => 0]);
        }

        $this->actingAs($this->northStaff)->get(route('pos-shifts.index'))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 1));
        $this->actingAs($this->boss)->get(route('pos-shifts.index'))->assertInertia(fn (Assert $page) => $page->has('shifts.data', 2));

        $southShift = PosShift::where('branch_id', $this->south->id)->first();
        $this->actingAs($this->northStaff)->get(route('pos-shifts.show', $southShift))->assertForbidden();
    });

    it('lists and opens only the employees of the own branch', function () {
        $this->actingAs($this->northStaff)->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page->where('users.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['branch_id'] === $this->north->id)));

        $this->actingAs($this->northStaff)->get(route('users.show', $this->southStaff))->assertForbidden();
        $this->actingAs($this->boss)->get(route('users.show', $this->southStaff))->assertOk();
    });
});

describe('reports', function () {
    it('keeps the sales report to the own branch for restricted users', function () {
        branchOrder($this->north, 'N-1', $this->northStaff);
        branchOrder($this->south, 'S-1', $this->southStaff);

        $this->actingAs($this->northStaff)->get(route('reports.sales', ['branch_id' => $this->south->id]))
            ->assertInertia(fn (Assert $page) => $page->where('branchId', null)->where('stats.totalOrders', 1));
        $this->actingAs($this->boss)->get(route('reports.sales'))
            ->assertInertia(fn (Assert $page) => $page->where('stats.totalOrders', 2));
    });
});
