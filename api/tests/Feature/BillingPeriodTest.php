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

class BillingPeriodTest extends TestCase
{
    use RefreshDatabase;

    private function lease(int $months, string $start, float $rent = 150000, ?string $end = null): int
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
            'billing_period_months' => $months,
            'billing_day' => 1,
        ]))->assertCreated()->json('data.id');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_monthly_lease_starting_mid_month_is_charged_in_full(): void
    {
        // The bug this replaced: starting on the 2nd of a 31-day month used to
        // bill 30/31 of the rent. A term runs from its own start date.
        Carbon::setTestNow(Carbon::create(2026, 1, 15));

        $this->lease(1, '2026-01-02', 900000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('900000.00', $invoices[0]->amount);
        $this->assertSame('2026-01-02', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-02-01', $invoices[0]->period_end->toDateString());
    }

    public function test_a_mid_month_monthly_lease_repeats_on_its_own_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 15));
        $this->lease(1, '2026-01-02', 900000);
        $user = User::first();
        $service = app(InvoiceService::class);

        $this->assertCount(1, $service->generateDue($user));

        Carbon::setTestNow(Carbon::create(2026, 2, 3));
        $raised = $service->generateDue($user);

        $this->assertCount(2, $raised);
        $this->assertEquals('900000.00', $raised[1]->amount);
        $this->assertSame('2026-02-02', $raised[1]->period_start->toDateString());
    }

    public function test_a_six_month_lease_raises_one_invoice_for_the_whole_term(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));

        $this->lease(6, '2026-01-01', 150000, '2026-06-30');

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('900000.00', $invoices[0]->amount);
        $this->assertSame('2026-01-01', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-06-30', $invoices[0]->period_end->toDateString());
    }

    public function test_a_quarterly_lease_bills_once_per_quarter_as_time_passes(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5));
        $this->lease(3, '2026-01-01', 100000);
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

        $this->lease(6, '2026-03-16', 200000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertSame('2026-03-16', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-09-15', $invoices[0]->period_end->toDateString());
        $this->assertEquals('1200000.00', $invoices[0]->amount);
    }

    public function test_a_lease_starting_on_the_first_still_covers_the_calendar_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 20));

        $this->lease(1, '2026-04-01', 300000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('300000.00', $invoices[0]->amount);
        $this->assertSame('2026-04-01', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-04-30', $invoices[0]->period_end->toDateString());
    }

    public function test_no_period_is_ever_prorated_merely_for_starting_mid_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 25));

        foreach ([2, 9, 17, 28] as $day) {
            $id = $this->lease(1, sprintf('2026-03-%02d', $day), 500000);
            $lease = \App\Models\Lease::find($id);
            $invoice = app(InvoiceService::class)->generateForLease($lease, Carbon::create(2026, 3, $day));

            $this->assertEquals(
                '500000.00',
                $invoice->amount,
                "a lease starting on the {$day}th should still cost the full rent"
            );
        }
    }

    public function test_a_period_cut_short_by_the_lease_ending_is_prorated(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));

        // A three-month term, but the lease ends after one month. The tenant
        // is not charged for time they will not be there.
        $this->lease(3, '2026-01-01', 300000, '2026-01-31');

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        // 31 days of the 90-day term, at 900,000 for the full term.
        $this->assertEqualsWithDelta(310000, (float) $invoices[0]->amount, 500);
        $this->assertLessThan(900000, (float) $invoices[0]->amount);
    }

    public function test_billing_is_idempotent_on_every_cycle(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 15));
        $this->lease(12, '2026-01-01', 100000);
        $user = User::first();
        $service = app(InvoiceService::class);

        $service->generateDue($user);
        $service->generateDue($user);
        $service->generateDue($user);

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_an_arbitrary_negotiated_term_works(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 20));

        // Four months — not one of the conventional terms, and perfectly valid.
        $this->lease(4, '2026-03-16', 250000);

        $invoices = app(InvoiceService::class)->generateDue(User::first());

        $this->assertCount(1, $invoices);
        $this->assertEquals('1000000.00', $invoices[0]->amount);
        $this->assertSame('2026-03-16', $invoices[0]->period_start->toDateString());
        $this->assertSame('2026-07-15', $invoices[0]->period_end->toDateString());
    }

    public function test_a_five_month_term_repeats_on_its_own_rhythm(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 5));
        $this->lease(5, '2026-01-01', 100000);
        $user = User::first();
        $service = app(InvoiceService::class);

        $this->assertCount(1, $service->generateDue($user));

        Carbon::setTestNow(Carbon::create(2026, 5, 20));
        $this->assertCount(1, $service->generateDue($user), 'still inside the first term');

        Carbon::setTestNow(Carbon::create(2026, 6, 2));
        $raised = $service->generateDue($user);
        $this->assertCount(2, $raised);
        $this->assertSame('2026-06-01', $raised[1]->period_start->toDateString());
        $this->assertSame('2026-10-31', $raised[1]->period_end->toDateString());
    }

    public function test_a_term_longer_than_two_years_is_rejected(): void
    {
        $user = User::factory()->create();
        $property = \App\Models\Property::factory()->for($user, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->for($user, 'landlord')->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'billing_period_months' => 36,
        ])->assertStatus(422);
    }

    public function test_the_lease_endpoint_reports_what_one_invoice_will_cost(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 10));
        $id = $this->lease(6, '2026-01-01', 150000);

        $this->getJson("/api/leases/{$id}")
            ->assertOk()
            ->assertJsonPath('data.billing_period_months', 6)
            ->assertJsonPath('data.period_amount', '900000.00');
    }
}
