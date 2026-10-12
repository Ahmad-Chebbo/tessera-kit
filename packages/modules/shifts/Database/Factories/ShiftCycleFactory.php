<?php

namespace App\Modules\Shifts\Database\Factories;

use App\Modules\Shifts\Models\ShiftCycle;
use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftCycle>
 */
class ShiftCycleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => fn (array $attributes) => User::find($attributes['user_id'])->company_id,
            'shift_template_id' => fn (array $attributes) => ShiftTemplate::factory()->create(['company_id' => $attributes['company_id']])->id,
            'weekdays' => [1, 2, 3, 4, 5],
            'starts_on' => now()->toDateString(),
            'ends_on' => null,
        ];
    }
}
