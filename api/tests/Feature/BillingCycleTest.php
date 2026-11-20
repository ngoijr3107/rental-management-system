<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingCycleTest extends TestCase
{
    use RefreshDatabase;

    private function lease(string $cycle, string $start, float $rent = 150000, ?string $end = null): int
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => $rent]);
        $tenant = Tenant::factory()->for($user, 'landlord')->create();

        Sanctum::actingAs($user);

        return $this->postJson('/api/leases', array_filter([
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start,
            'end_date' => $end,
            'billing_cycle' => $cycle,
            'billing_day' => 1,
        ]))->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_six_month_lease_raises_one_invoice_for_the_whole_term(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));

        $this->lease('semi_annual', '2026-01-01', 150000, '2026-06-30');

        $invoices = app(InvoiceService::class)
            ->generateDue(auth()->user() ?? User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('900000.00', $invoices[0]->amount);
        $this->assertSame('2026-01-01', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-06-30', $invoices[0]->period_end->toDateString());
    }

    public function test_a_quarterly_lease_bills_once_per_quarter_as_time_passes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5));
        $this->lease('quarterly', '2026-01-01', 100000);
        $user = User::first();
        $service = app(InvoiceService::class);

        $this->assertCount(1, $service->generateDue($user));

        // Still inside the first quarter — nothing new.
        Carbon::setTestNow(Carbon::create(2026, 2, 20));
        $this->assertCount(1, $service->generateDue($user));

        // Second quarter has begun.
        Carbon::setTestNow(Carbon::create(2026, 4, 2));
        $raised = $service->generateDue($user);
        $this->assertCount(2, $raised);
        $this->assertEquals('300000.00', $raised[1]->amount);
        $this->assertSame('2026-04-01', $raised[1]->period_start->toDateString());
    }

    public function test_a_non_monthly_period_runs_from_the_start_date_not_the_calendar(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 20));

        $this->lease('semi_annual', '2026-03-16', 200000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertSame('2026-03-16', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-09-15', $invoices[0]->period_end->toDateString());
        $this->assertEquals('1200000.00', $invoices[0]->amount);
    }

    public function test_monthly_leases_keep_billing_on_calendar_months(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 20));

        $this->lease('monthly', '2026-04-01', 300000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('300000.00', $invoices[0]->amount);
        $this->assertSame('2026-04-30', $invoices[0]->period_end->toDateString());
    }

    public function test_a_short_final_period_is_prorated(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));

        // Three-month cycle, but the lease ends after roughly one month.
        $this->lease('quarterly', '2026-01-01', 300000, '2026-01-31');

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        // 31 of 90 days at 900,000 for a full quarter.
        $this->assertEquals(310000.0, round((float) $invoices[0]->amount, 0));
    }

    public function test_billing_is_idempotent_on_every_cycle(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $this->lease('annual', '2026-01-01', 100000);
        $user = User::first();
        $service = app(InvoiceService::class);

        $service->generateDue($user);
        $service->generateDue($user);
        $service->generateDue($user);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_the_lease_endpoint_reports_what_one_invoice_will_cost(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));
        $id = $this->lease('semi_annual', '2026-01-01', 150000);

        $this->getJson("/api/leases/{$id}")
            ->assertOk()
            ->assertJsonPath('data.billing_cycle', 'semi_annual')
            ->assertJsonPath('data.period_amount', '900000.00');
    }
}
