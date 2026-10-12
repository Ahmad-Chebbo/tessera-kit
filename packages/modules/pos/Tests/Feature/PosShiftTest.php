<?php

use App\Modules\Catalog\Models\Product;
use App\Modules\Customers\Models\Customer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Orders\Enums\OrderStatus;
use App\Modules\Orders\Models\Order;
use App\Modules\Pos\Actions\CheckoutAction;
use App\Modules\Pos\Models\PosShift;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Location;
use App\Modules\Tenant\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function cashDrawerStaff(Tenant $tenant, Branch $branch, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

function cashDrawerSale(User $cashier, Branch $branch, Product $product, string $paymentType = 'cash', ?int $customerId = null): Order
{
    return app(CheckoutAction::class)->execute(
        branch: $branch,
        cartItems: [['product_id' => $product->id, 'quantity' => 2]],
        cashReceived: 100,
        cashier: $cashier,
        customerId: $customerId,
        paymentType: $paymentType,
    );
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->location = Location::factory()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branch->id, 'type' => 'branch']);
    $this->manager = cashDrawerStaff($this->tenant, $this->branch, ['view shifts', 'manage shifts', 'view shift reports', 'open pos shift', 'view activity log']);
    $this->cashier = cashDrawerStaff($this->tenant, $this->branch, ['open pos shift', 'access pos']);

    $this->other = Tenant::factory()->create();
    $this->otherBranch = Branch::factory()->create(['tenant_id' => $this->other->id]);
    $this->otherManager = cashDrawerStaff($this->other, $this->otherBranch, ['view shifts', 'manage shifts', 'view shift reports']);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'base_price' => 25, 'cost_price' => 10, 'is_active' => true]);
    StockMovement::factory()->create(['tenant_id' => $this->tenant->id, 'product_id' => $this->product->id, 'location_id' => $this->location->id, 'quantity' => 100]);
});

describe('pos shifts', function () {
    it('opens a shift with an opening float and blocks a second one', function () {
        $this->actingAs($this->cashier)->post(route('pos-shifts.open'), ['opening_float' => 150])->assertSessionHas('success');

        $shift = PosShift::firstOrFail();
        expect($shift->user_id)->toBe($this->cashier->id)
            ->and($shift->branch_id)->toBe($this->branch->id)
            ->and((float) $shift->opening_float)->toBe(150.0)
            ->and($shift->isOpen())->toBeTrue();

        $this->actingAs($this->cashier)->post(route('pos-shifts.open'), ['opening_float' => 10])->assertSessionHas('error');
        expect(PosShift::count())->toBe(1)
            ->and(ActivityLog::where('event', 'shift.opened')->count())->toBe(1);
    });

    it('validates the opening float', function () {
        $this->actingAs($this->cashier)->post(route('pos-shifts.open'), ['opening_float' => -5])->assertSessionHasErrors('opening_float');
    });

    it('needs the open permission', function () {
        $viewer = cashDrawerStaff($this->tenant, $this->branch, ['view shifts']);

        $this->actingAs($viewer)->post(route('pos-shifts.open'), ['opening_float' => 5])->assertForbidden();
    });

    it('links sales to the open shift', function () {
        $shift = PosShift::factory()->create(['user_id' => $this->cashier->id]);

        $order = cashDrawerSale($this->cashier, $this->branch, $this->product);

        expect($order->pos_shift_id)->toBe($shift->id);
    });

    it('lets sales through without a shift while the requirement is off', function () {
        $order = cashDrawerSale($this->cashier, $this->branch, $this->product);

        expect($order->pos_shift_id)->toBeNull();
    });

    it('blocks checkout without an open shift when the tenant requires one', function () {
        $this->tenant->update(['require_pos_shift' => true]);

        $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'cash_received' => 100,
        ])->assertUnprocessable()->assertJsonPath('error', 'Open a shift before selling.');

        PosShift::factory()->create(['user_id' => $this->cashier->id]);

        $this->actingAs($this->cashier)->postJson(route('pos.checkout'), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'cash_received' => 100,
        ])->assertOk();
    });

    it('does not block offline syncs when a shift is required', function () {
        $this->tenant->update(['require_pos_shift' => true]);

        $this->actingAs($this->cashier)->postJson(route('pos.sync'), ['sales' => [[
            'offline_id' => 'sale-1',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'cash_received' => 100,
        ]]])->assertOk()->assertJsonPath('synced', 1);
    });

    it('closes a shift with expected cash, variance and a snapshot report', function () {
        $shift = PosShift::factory()->create(['user_id' => $this->cashier->id, 'opening_float' => 100]);

        $cash = cashDrawerSale($this->cashier, $this->branch, $this->product);
        $voided = cashDrawerSale($this->cashier, $this->branch, $this->product);
        $voided->update(['status' => OrderStatus::Voided]);
        $customer = Customer::create(['tenant_id' => $this->tenant->id, 'name' => 'Debtor']);
        cashDrawerSale($this->cashier, $this->branch, $this->product, 'debt', $customer->id);

        $this->actingAs($this->cashier)
            ->post(route('pos-shifts.close', $shift), ['counted_cash' => 140, 'notes' => 'Short by ten'])
            ->assertRedirect(route('pos-shifts.show', $shift));

        $shift->refresh();
        expect($shift->status)->toBe('closed')
            ->and($shift->open_user_id)->toBeNull()
            ->and((float) $shift->expected_cash)->toBe(150.0)
            ->and((float) $shift->variance)->toBe(-10.0)
            ->and($shift->summary)->toMatchArray([
                'orders' => 2, 'items' => 4, 'gross_sales' => 100.0, 'cash_sales' => 50.0,
                'credit_sales' => 50.0, 'voided_orders' => 1, 'voided_total' => 50.0,
            ])
            ->and(ActivityLog::where('event', 'shift.closed')->count())->toBe(1);

        $this->postJson(route('pos.checkout'), ['items' => [['product_id' => $this->product->id, 'quantity' => 1]], 'cash_received' => 50])->assertOk();
        expect(Order::latest('id')->first()->pos_shift_id)->toBeNull();
    });

    it('cannot close a shift twice or close someone else\'s without permission', function () {
        $shift = PosShift::factory()->create(['user_id' => $this->cashier->id]);
        $coworker = cashDrawerStaff($this->tenant, $this->branch, ['open pos shift']);

        $this->actingAs($coworker)->post(route('pos-shifts.close', $shift), ['counted_cash' => 1])->assertForbidden();
        $this->actingAs($this->manager)->post(route('pos-shifts.close', $shift), ['counted_cash' => 100])->assertRedirect();
        $this->actingAs($this->manager)->post(route('pos-shifts.close', $shift), ['counted_cash' => 100])->assertSessionHas('error');
    });

    it('lets a cashier open a new shift after closing the previous one', function () {
        $shift = PosShift::factory()->create(['user_id' => $this->cashier->id]);
        $this->actingAs($this->cashier)->post(route('pos-shifts.close', $shift), ['counted_cash' => 100]);

        $this->actingAs($this->cashier)->post(route('pos-shifts.open'), ['opening_float' => 20])->assertSessionHas('success');
        expect(PosShift::count())->toBe(2);
    });

    it('shows the closing report to the owner and managers only', function () {
        $shift = PosShift::factory()->closed()->create(['user_id' => $this->cashier->id, 'summary' => ['orders' => 0]]);
        $coworker = cashDrawerStaff($this->tenant, $this->branch, ['open pos shift']);

        $this->actingAs($this->cashier)->get(route('pos-shifts.show', $shift))
            ->assertInertia(fn (Assert $page) => $page->component('PosShifts/Show')->where('shift.status', 'closed')->where('can.close', false));
        $this->actingAs($this->manager)->get(route('pos-shifts.show', $shift))->assertOk();
        $this->actingAs($coworker)->get(route('pos-shifts.show', $shift))->assertForbidden();
        $this->actingAs($this->otherManager)->get(route('pos-shifts.show', $shift))->assertForbidden();
    });

    it('lists only own shifts without the reports permission', function () {
        PosShift::factory()->closed()->create(['user_id' => $this->cashier->id]);
        PosShift::factory()->closed()->create(['user_id' => $this->manager->id]);
        PosShift::factory()->closed()->create(['user_id' => $this->otherManager->id]);

        $this->actingAs($this->cashier)->get(route('pos-shifts.index'))
            ->assertInertia(fn (Assert $page) => $page->component('PosShifts/Index')->has('shifts.data', 1));
        $this->actingAs($this->manager)->get(route('pos-shifts.index'))
            ->assertInertia(fn (Assert $page) => $page->has('shifts.data', 2)->where('can.seeAll', true));
    });

    it('shares the open shift with every page', function () {
        $shift = PosShift::factory()->create(['user_id' => $this->cashier->id]);

        $this->actingAs($this->cashier)->get(route('pos-shifts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('posShift.id', $shift->id)->where('requirePosShift', false));
    });

    it('stores the shift requirement in settings', function () {
        $owner = cashDrawerStaff($this->tenant, $this->branch, ['manage settings']);

        $this->actingAs($owner)->put(route('settings.update'), [
            'name' => $this->tenant->name, 'currency' => 'USD', 'require_pos_shift' => true,
        ])->assertSessionHasNoErrors();

        expect($this->tenant->fresh()->require_pos_shift)->toBeTrue();
    });
});
