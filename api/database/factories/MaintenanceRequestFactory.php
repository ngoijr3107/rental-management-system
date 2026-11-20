<?php

namespace Database\Factories;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class MaintenanceRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => null,
            'title' => fake()->randomElement([
                'Leaking kitchen tap',
                'Broken window latch',
                'Water heater not working',
                'Blocked drain',
                'Faulty light switch',
            ]),
            'description' => fake()->sentence(),
            'priority' => fake()->randomElement(MaintenancePriority::values()),
            'status' => MaintenanceStatus::Open,
            'cost' => null,
            'reported_at' => now()->subDays(fake()->numberBetween(0, 20))->toDateString(),
            'resolved_at' => null,
        ];
    }
}
