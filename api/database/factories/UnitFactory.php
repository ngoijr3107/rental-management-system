<?php

namespace Database\Factories;

use App\Enums\UnitStatus;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    public function definition(): array
    {
        $rent = fake()->numberBetween(200, 2000) * 100;

        return [
            'property_id' => Property::factory(),
            'label' => 'Unit '.fake()->unique()->numerify('###'),
            'bedrooms' => fake()->numberBetween(1, 4),
            'bathrooms' => fake()->numberBetween(1, 3),
            'size_sqm' => fake()->numberBetween(30, 180),
            'rent_amount' => $rent,
            'deposit_amount' => $rent,
            'status' => UnitStatus::Vacant,
            'description' => null,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => UnitStatus::Occupied]);
    }
}
