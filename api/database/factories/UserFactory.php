<?php

namespace Database\Factories;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    /**
     * Defaults to an account owner. Role and is_active are set explicitly
     * rather than left to the column defaults, so a freshly made model has
     * them in memory — permission checks run against the instance.
     */
    public function definition(): array
    {
        return [
            'account_owner_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => fake()->numerify('+2557########'),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Owner->value,
            'is_active' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }

    public function member(int $ownerId, Role $role = Role::Agent): static
    {
        return $this->state(fn () => [
            'account_owner_id' => $ownerId,
            'role' => $role->value,
        ]);
    }
}
