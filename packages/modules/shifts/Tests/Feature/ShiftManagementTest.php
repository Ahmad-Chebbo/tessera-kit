<?php

use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftCycle;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use App\Modules\Tenant\Models\Branch;
use App\Modules\Tenant\Models\Tenant;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

function staffWith(Tenant $tenant, Branch $branch, array $permissions): User
{
    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission);
    }

    return User::factory()->create(['tenant_id' => $tenant->id, 'branch_id' => $branch->id])->givePermissionTo($permissions);
}

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager = staffWith($this->tenant, $this->branch, ['view shifts', 'manage shifts', 'view shift reports', 'open pos shift', 'view activity log']);
    $this->cashier = staffWith($this->tenant, $this->branch, ['open pos shift', 'access pos']);

    $this->other = Tenant::factory()->create();
    $this->otherBranch = Branch::factory()->create(['tenant_id' => $this->other->id]);
    $this->otherManager = staffWith($this->other, $this->otherBranch, ['view shifts', 'manage shifts', 'view shift reports']);

});

describe('shift roster', function () {
    it('creates, updates and deletes shift templates', function () {
        $this->actingAs($this->manager)
            ->post(route('shifts.templates.store'), ['name' => 'Morning', 'starts_at' => '08:00', 'ends_at' => '16:00', 'is_active' => true])
            ->assertRedirect();

        $template = ShiftTemplate::where('tenant_id', $this->tenant->id)->firstOrFail();
        expect($template->durationMinutes())->toBe(480);

        $this->actingAs($this->manager)
            ->put(route('shifts.templates.update', $template), ['name' => 'Morning', 'starts_at' => '07:00', 'ends_at' => '15:00'])
            ->assertRedirect();
        expect($template->fresh()->starts_at)->toBe('07:00');

        $this->actingAs($this->manager)->delete(route('shifts.templates.destroy', $template))->assertRedirect();
        expect(ShiftTemplate::count())->toBe(0);
    });

    it('treats a shift ending before it starts as overnight', function () {
        $template = ShiftTemplate::factory()->make(['starts_at' => '22:00', 'ends_at' => '06:00']);

        expect($template->durationMinutes())->toBe(480);
    });

    it('validates templates', function () {
        ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Morning']);

        $this->actingAs($this->manager)
            ->post(route('shifts.templates.store'), ['name' => 'Morning', 'starts_at' => '8am', 'ends_at' => '08:00'])
            ->assertSessionHasErrors(['name', 'starts_at']);

        $this->actingAs($this->manager)
            ->post(route('shifts.templates.store'), ['name' => 'Flat', 'starts_at' => '08:00', 'ends_at' => '08:00'])
            ->assertSessionHasErrors('ends_at');
    });

    it('allows the same shift name in another tenant', function () {
        ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Morning']);

        $this->actingAs($this->otherManager)
            ->post(route('shifts.templates.store'), ['name' => 'Morning', 'starts_at' => '08:00', 'ends_at' => '16:00'])
            ->assertSessionHasNoErrors();
    });

    it('assigns an employee to a shift once per day and logs it', function () {
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        $payload = ['user_id' => $this->cashier->id, 'shift_template_id' => $template->id, 'date' => '2026-10-12'];

        $this->actingAs($this->manager)->post(route('shifts.assignments.store'), $payload)->assertRedirect();
        $this->actingAs($this->manager)->post(route('shifts.assignments.store'), $payload)->assertRedirect();

        expect(ShiftAssignment::count())->toBe(1)
            ->and(ActivityLog::where('event', 'shift.assigned')->count())->toBe(1);
    });

    it('rejects assigning employees or shifts from another tenant', function () {
        $foreignTemplate = ShiftTemplate::factory()->create(['tenant_id' => $this->other->id]);

        $this->actingAs($this->manager)
            ->post(route('shifts.assignments.store'), ['user_id' => $this->otherManager->id, 'shift_template_id' => $foreignTemplate->id, 'date' => '2026-10-12'])
            ->assertSessionHasErrors(['user_id', 'shift_template_id']);
    });

    it('does not let another tenant remove an assignment', function () {
        $assignment = ShiftAssignment::factory()->create(['user_id' => $this->cashier->id]);

        $this->actingAs($this->otherManager)->delete(route('shifts.assignments.destroy', $assignment))->assertForbidden();
        $this->actingAs($this->manager)->delete(route('shifts.assignments.destroy', $assignment))->assertRedirect();
        expect(ShiftAssignment::count())->toBe(0);
    });

    it('generates assignments from a weekly cycle without duplicating them', function () {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 08:00'));
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->manager)->post(route('shifts.cycles.store'), [
            'user_id' => $this->cashier->id,
            'shift_template_id' => $template->id,
            'weekdays' => [1, 3],
            'starts_on' => '2026-10-12',
            'ends_on' => '2026-10-25',
        ])->assertRedirect();

        $dates = ShiftAssignment::orderBy('date')->get()->pluck('date')->all();
        expect($dates)->toBe(['2026-10-12', '2026-10-14', '2026-10-19', '2026-10-21']);

        $cycle = ShiftCycle::firstOrFail();
        $this->actingAs($this->manager)->post(route('shifts.cycles.generate', $cycle))->assertRedirect();
        expect(ShiftAssignment::count())->toBe(4);

        $this->actingAs($this->manager)->delete(route('shifts.cycles.destroy', $cycle))->assertRedirect();
        expect(ShiftAssignment::count())->toBe(0);
    });

    it('validates cycles', function () {
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->manager)->post(route('shifts.cycles.store'), [
            'user_id' => $this->cashier->id, 'shift_template_id' => $template->id,
            'weekdays' => [], 'starts_on' => '2026-10-12', 'ends_on' => '2026-10-01',
        ])->assertSessionHasErrors(['weekdays', 'ends_on']);

        $this->actingAs($this->manager)->post(route('shifts.cycles.store'), [
            'user_id' => $this->cashier->id, 'shift_template_id' => $template->id,
            'weekdays' => [7], 'starts_on' => '2026-10-12',
        ])->assertSessionHasErrors('weekdays.0');
    });

    it('shows the week roster scoped to the tenant', function () {
        $template = ShiftTemplate::factory()->create(['tenant_id' => $this->tenant->id]);
        ShiftAssignment::factory()->create(['user_id' => $this->cashier->id, 'shift_template_id' => $template->id, 'date' => '2026-10-13']);
        ShiftAssignment::factory()->create(['user_id' => $this->otherManager->id, 'date' => '2026-10-13']);

        $this->actingAs($this->manager)->get(route('shifts.index', ['week' => '2026-10-14']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Shifts/Index')
                ->where('weekStart', '2026-10-12')
                ->has('days', 7)
                ->has('assignments', 1)
                ->where('can.manage', true));
    });

    it('requires permissions', function () {
        $this->actingAs($this->cashier)->get(route('shifts.index'))->assertForbidden();
        $this->actingAs($this->cashier)->post(route('shifts.templates.store'), [])->assertForbidden();
    });
});
