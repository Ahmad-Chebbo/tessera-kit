<?php

namespace App\Modules\Shifts\Actions;

use App\Modules\Company\Services\BranchAccess;
use App\Modules\Shifts\Models\ShiftCycle;
use App\Modules\Team\Models\User;
use Carbon\CarbonImmutable;

class CreateShiftCycleAction
{
    public const GENERATE_WEEKS = 8;

    public function __construct(private readonly BranchAccess $access) {}

    /**
     * Repeats a shift on chosen weekdays for an employee and fills the next weeks of the roster.
     *
     * @param  array<string, mixed>  $data  user_id, shift_template_id, weekdays, starts_on and optional ends_on.
     */
    public function execute(User $actor, array $data): ShiftCycle
    {
        $this->access->ensure($actor, User::query()->whereKey($data['user_id'])->value('branch_id'));

        $cycle = ShiftCycle::create([
            ...$data,
            'weekdays' => collect($data['weekdays'])->map(fn ($day): int => (int) $day)->sort()->values()->all(),
            'company_id' => $actor->company_id,
        ]);

        $this->generateAhead($cycle);

        return $cycle;
    }

    /**
     * @return int How many assignments were created.
     */
    public function generateAhead(ShiftCycle $cycle): int
    {
        $from = CarbonImmutable::today();

        return $cycle->generate($from, $from->addWeeks(self::GENERATE_WEEKS));
    }
}
