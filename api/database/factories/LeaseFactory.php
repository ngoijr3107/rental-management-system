<?php

namespace Database\Factories;

use App\Enums\LeaseStatus;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaseFactory extends Factory
{
    public function definition(): array
    {
        $rent = fake()->numberBetween(200, 2000) * 100;

        return [
            'unit_id' => Unit::factory(),
            'tenant_id' => Tenant::factory(),
            'start_date' => now()->subMonths(3)->startOfMonth(),
            'end_date' => now()->addMonths(9)->endOfMonth(),
            'rent_amount' => $rent,
            'deposit_amount' => $rent,
            'billing_day' => 5,
            'status' => LeaseStatus::Active,
            'notes' => null,
        ];
    }

    public function terminated(): static
    {
        return $this->state(fn () => [
            'status' => LeaseStatus::Terminated,
            'terminated_at' => now()->subDays(10),
        ]);
    }
}
