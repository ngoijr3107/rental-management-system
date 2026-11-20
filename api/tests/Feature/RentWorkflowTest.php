<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function landlordWithUnit(): array
    {
        $user = User::factory()->create();
        $property = Property::factory()->for($user, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 300000]);
        $tenant = Tenant::factory()->for($user, 'landlord')->create();

        Sanctum::actingAs($user);

        return [$user, $unit, $tenant];
    }

    public function test_creating_a_lease_marks_the_unit_occupied(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        $response = $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'billing_day' => 5,
        ]);

        $response->assertCreated()->assertJsonPath('data.status', LeaseStatus::Active->value);
        $this->assertSame(UnitStatus::Occupied, $unit->fresh()->status);
    }

    public function test_a_unit_cannot_be_let_twice(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        $payload = [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ];

        $this->postJson('/api/leases', $payload)->assertCreated();
        $this->postJson('/api/leases', $payload)->assertStatus(422);
    }

    public function test_invoice_generation_is_idempotent(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated()->assertJsonPath('count', 1);
        $this->postJson('/api/invoices/generate')->assertCreated();

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_payments_update_the_invoice_status_and_reject_overpayment(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');

        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 100000,
            'method' => 'cash',
        ])->assertCreated();

        $partial = $this->getJson("/api/invoices/{$invoiceId}")
            ->assertJsonPath('data.status', InvoiceStatus::PartiallyPaid->value);

        $this->assertEquals(200000, $partial->json('data.balance'));

        // More than the remaining balance must be rejected.
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 500000,
            'method' => 'cash',
        ])->assertStatus(422);

        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 200000,
            'method' => 'mobile_money',
        ])->assertCreated();

        $this->getJson("/api/invoices/{$invoiceId}")
            ->assertJsonPath('data.status', InvoiceStatus::Paid->value);
    }

    public function test_a_mid_month_lease_is_charged_the_full_agreed_rent(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        Carbon::setTestNow(Carbon::create(2026, 4, 20));

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-04-16',
            'rent_amount' => 300000,
        ])->assertCreated();

        $this->postJson('/api/invoices/generate', ['month' => '2026-04'])->assertCreated();

        // The term runs 16 April to 15 May at the agreed rent — no discount
        // for starting part-way through the calendar month.
        $this->getJson('/api/invoices')
            ->assertJsonPath('data.0.amount', '300000.00')
            ->assertJsonPath('data.0.period_start', '2026-04-16')
            ->assertJsonPath('data.0.period_end', '2026-05-15');

        Carbon::setTestNow();
    }

    public function test_a_landlord_cannot_see_another_landlords_data(): void
    {
        $other = User::factory()->create();
        $otherProperty = Property::factory()->for($other, 'landlord')->create();

        [$user] = $this->landlordWithUnit();

        $this->getJson("/api/properties/{$otherProperty->id}")->assertNotFound();
        $this->getJson('/api/properties')->assertJsonCount(1, 'data');
    }

    public function test_terminating_a_lease_frees_the_unit(): void
    {
        [$user, $unit, $tenant] = $this->landlordWithUnit();

        $leaseId = $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->subMonth()->toDateString(),
        ])->json('data.id');

        $this->postJson("/api/leases/{$leaseId}/terminate")
            ->assertOk()
            ->assertJsonPath('data.status', LeaseStatus::Terminated->value);

        $this->assertSame(UnitStatus::Vacant, $unit->fresh()->status);
    }
}
