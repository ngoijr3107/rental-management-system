<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Lease;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->startOfMonth();

        return [
            'lease_id' => Lease::factory(),
            'invoice_number' => 'INV-'.$start->format('Ym').'-'.fake()->unique()->numerify('####'),
            'period_start' => $start->toDateString(),
            'period_end' => $start->copy()->endOfMonth()->toDateString(),
            'due_date' => $start->copy()->setDay(5)->toDateString(),
            'amount' => fake()->numberBetween(200, 2000) * 100,
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ];
    }
}
