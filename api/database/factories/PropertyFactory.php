<?php

namespace Database\Factories;

use App\Enums\PropertyType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PropertyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->streetName().' Court',
            'type' => fake()->randomElement(PropertyType::values()),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
