<?php

namespace App\Modules\Pos\Database\Factories;

use App\Modules\Pos\Models\PosShift;
use App\Modules\Team\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosShift>
 */
class PosShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'tenant_id' => fn (array $attributes) => User::find($attributes['user_id'])->tenant_id,
            'branch_id' => fn (array $attributes) => User::find($attributes['user_id'])->branch_id,
            'open_user_id' => fn (array $attributes) => $attributes['user_id'],
            'status' => PosShift::OPEN,
            'opened_at' => now()->subHours(2),
            'opening_float' => 100,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => PosShift::CLOSED,
            'open_user_id' => null,
            'closed_at' => now(),
            'expected_cash' => 100,
            'counted_cash' => 100,
            'variance' => 0,
        ]);
    }
}
