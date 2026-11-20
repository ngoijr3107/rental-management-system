<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'recorded_by' => null,
            'amount' => fake()->numberBetween(200, 2000) * 100,
            'paid_at' => now()->toDateString(),
            'method' => fake()->randomElement(PaymentMethod::values()),
            'reference' => fake()->optional()->bothify('REF-####??'),
            'notes' => null,
        ];
    }
}
