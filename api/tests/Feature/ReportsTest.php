<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function portfolio(): array
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user, 'landlord')->create();
        $let = Unit::factory()->for($property)->create(['label' => 'A1', 'rent_amount' => 300000]);
        $empty = Unit::factory()->for($property)->create(['label' => 'A2', 'rent_amount' => 200000]);
        $tenant = Tenant::factory()->for($user, 'landlord')->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/leases', [
            'unit_id' => $let->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated();

        return [$user, $let, $empty, $tenant];
    }

    public function test_rent_roll_separates_contracted_income_from_vacancy_loss(): void
    {
        $this->portfolio();

        $response = $this->getJson('/api/reports/rent-roll')->assertOk();

        $this->assertEquals(2, $response->json('totals.units'));
        $this->assertEquals(1, $response->json('totals.occupied'));
        $this->assertEquals(300000, $response->json('totals.contracted_monthly'));
        $this->assertEquals(200000, $response->json('totals.vacancy_loss_monthly'));
    }

    public function test_arrears_are_bucketed_and_shrink_when_paid(): void
    {
        $this->portfolio();

        $before = $this->getJson('/api/reports/arrears')->assertOk();
        $this->assertEquals(300000, $before->json('totals.total'));

        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 300000,
            'method' => 'cash',
        ])->assertCreated();

        $after = $this->getJson('/api/reports/arrears')->assertOk();
        $this->assertEquals(0, $after->json('totals.total'));
        $this->assertSame([], $after->json('rows'));
    }

    public function test_collections_returns_one_row_per_month_and_a_rate(): void
    {
        $this->portfolio();

        $response = $this->getJson('/api/reports/collections?months=6')->assertOk();

        $this->assertCount(6, $response->json('rows'));
        $this->assertEquals(300000, $response->json('totals.billed'));
        $this->assertEquals(0, $response->json('totals.collected'));
        $this->assertEquals(0.0, $response->json('totals.rate'));
    }

    public function test_vacancy_counts_only_units_without_a_live_lease(): void
    {
        $this->portfolio();

        $response = $this->getJson('/api/reports/vacancy')->assertOk();

        $this->assertEquals(1, $response->json('totals.vacant_units'));
        $this->assertEquals(2, $response->json('totals.total_units'));
        $this->assertEquals(200000, $response->json('totals.monthly_loss'));
    }

    public function test_property_performance_nets_collections_against_upkeep(): void
    {
        [$user, $let] = $this->portfolio();

        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 300000,
            'method' => 'cash',
        ])->assertCreated();

        $this->postJson('/api/maintenance-requests', [
            'unit_id' => $let->id,
            'title' => 'Leaking tap',
            'cost' => 50000,
        ])->assertCreated();

        $response = $this->getJson('/api/reports/property-performance')->assertOk();

        $this->assertEquals(300000, $response->json('totals.collected'));
        $this->assertEquals(50000, $response->json('totals.maintenance_cost'));
        $this->assertEquals(250000, $response->json('totals.net'));
    }

    public function test_tenant_statement_runs_a_balance_down_the_page(): void
    {
        [, , , $tenant] = $this->portfolio();

        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 100000,
            'method' => 'cash',
        ])->assertCreated();

        $response = $this->getJson("/api/reports/tenant-statement/{$tenant->id}")->assertOk();

        $entries = $response->json('entries');
        $this->assertCount(2, $entries);
        $this->assertEquals(300000, $entries[0]['balance']);
        $this->assertEquals(200000, $entries[1]['balance']);
        $this->assertEquals(200000, $response->json('totals.balance'));
    }

    public function test_reports_never_leak_another_landlords_portfolio(): void
    {
        $other = User::factory()->create();
        $otherProperty = Property::factory()->for($other, 'landlord')->create();
        Unit::factory()->for($otherProperty)->create(['rent_amount' => 999999]);

        $this->portfolio();

        $response = $this->getJson('/api/reports/rent-roll')->assertOk();
        $this->assertEquals(2, $response->json('totals.units'));
        $this->assertCount(1, $response->json('groups'));
    }
}
