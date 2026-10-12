<?php

use App\Modules\Company\Models\Branch;
use App\Modules\Company\Models\Company;
use App\Modules\Shifts\Actions\AssignShiftAction;
use App\Modules\Shifts\Actions\CreateShiftCycleAction;
use App\Modules\Shifts\Models\ShiftAssignment;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\ActivityLog;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-14 08:00:00', 'UTC'));

    $this->company = Company::factory()->create();
    $this->north = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->south = Branch::factory()->create(['company_id' => $this->company->id]);
    $this->boss = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->north->id])->givePermissionTo(Permission::findOrCreate('view all branches'));
    $this->northManager = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->north->id]);
    $this->southStaff = User::factory()->create(['company_id' => $this->company->id, 'branch_id' => $this->south->id]);
    $this->morning = ShiftTemplate::factory()->create(['company_id' => $this->company->id, 'starts_at' => '09:00', 'ends_at' => '17:00']);
});

describe('assigning a shift', function () {
    it('puts the employee on the shift once and logs the first time only', function () {
        $data = ['user_id' => $this->southStaff->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-15'];

        $first = app(AssignShiftAction::class)->execute($this->boss, $data);
        $second = app(AssignShiftAction::class)->execute($this->boss, $data);

        expect($second->id)->toBe($first->id)
            ->and($first->company_id)->toBe($this->company->id)
            ->and(ShiftAssignment::count())->toBe(1)
            ->and(ActivityLog::where('event', 'shift.assigned')->where('user_id', $this->boss->id)->count())->toBe(1);
    });

    it('stays inside the actor\'s branch', function () {
        app(AssignShiftAction::class)->execute($this->northManager, ['user_id' => $this->southStaff->id, 'shift_template_id' => $this->morning->id, 'date' => '2026-10-15']);
    })->throws(HttpException::class);
});

describe('creating a shift cycle', function () {
    it('repeats the shift on the chosen weekdays and fills the coming weeks', function () {
        $cycle = app(CreateShiftCycleAction::class)->execute($this->boss, [
            'user_id' => $this->southStaff->id, 'shift_template_id' => $this->morning->id,
            'weekdays' => ['3', '1'], 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-31',
        ]);

        $dates = ShiftAssignment::where('shift_cycle_id', $cycle->id)->orderBy('date')->pluck('date')->all();

        expect($cycle->weekdays)->toBe([1, 3])
            ->and($cycle->company_id)->toBe($this->company->id)
            ->and($dates)->toBe(['2026-10-14', '2026-10-19', '2026-10-21', '2026-10-26', '2026-10-28']);
    });

    it('generates ahead again without duplicating', function () {
        $action = app(CreateShiftCycleAction::class);
        $cycle = $action->execute($this->boss, [
            'user_id' => $this->southStaff->id, 'shift_template_id' => $this->morning->id,
            'weekdays' => [1], 'starts_on' => '2026-10-14', 'ends_on' => '2026-10-31',
        ]);

        expect($action->generateAhead($cycle))->toBe(0)->and(ShiftAssignment::count())->toBe(2);
    });

    it('stays inside the actor\'s branch', function () {
        app(CreateShiftCycleAction::class)->execute($this->northManager, [
            'user_id' => $this->southStaff->id, 'shift_template_id' => $this->morning->id, 'weekdays' => [1], 'starts_on' => '2026-10-14',
        ]);
    })->throws(HttpException::class);
});
