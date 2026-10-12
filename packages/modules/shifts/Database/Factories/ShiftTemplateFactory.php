<?php

namespace App\Modules\Shifts\Database\Factories;

use App\Modules\Shifts\Models\ShiftTemplate;
use App\Modules\Tenant\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftTemplate>
 */
class ShiftTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->unique()->word().' shift',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'is_active' => true,
        ];
    }
}
