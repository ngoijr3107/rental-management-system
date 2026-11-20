<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TenantFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('+2557########'),
            'national_id' => fake()->optional()->numerify('###########'),
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_phone' => fake()->numerify('+2557########'),
            'notes' => null,
        ];
    }
}
