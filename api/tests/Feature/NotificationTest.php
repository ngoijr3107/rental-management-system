<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\OutboundMessage;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private function letUnit(User $owner, string $start = null): array
    {
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 200000]);
        $tenant = Tenant::factory()->for($owner, 'landlord')->create(['phone' => '+255700111222']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => $start ?? now()->startOfMonth()->toDateString(),
            'billing_day' => 5,
        ])->assertCreated();

        return [$unit, $tenant];
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_raising_invoices_queues_a_message_to_each_tenant(): void
    {
        $owner = User::factory()->create();
        [, $tenant] = $this->letUnit($owner);

        $this->postJson('/api/invoices/generate')->assertCreated();

        $message = OutboundMessage::query()->where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('sms', $message->channel);
        $this->assertSame('+255700111222', $message->recipient);
        $this->assertSame('queued', $message->status);
    }

    public function test_recording_a_payment_alerts_the_landlord_and_receipts_the_tenant(): void
    {
        $owner = User::factory()->create();
        $this->letUnit($owner);
        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');

        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 50000, 'method' => 'mobile_money',
        ])->assertCreated();

        $this->assertTrue(
            Alert::query()->where('type', 'payment.received')->exists(),
            'the landlord should see a payment alert'
        );
        $this->assertTrue(
            OutboundMessage::query()->where('type', 'payment.receipt')->exists(),
            'the tenant should get a receipt'
        );
    }

    public function test_a_reminder_goes_out_the_agreed_number_of_days_before_due(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1));
        $owner = User::factory()->create();
        $this->letUnit($owner, '2026-03-01');
        $this->postJson('/api/invoices/generate')->assertCreated();

        $notify = app(NotificationService::class);

        // Due on the 5th; nothing three days out is the 2nd.
        Carbon::setTestNow(Carbon::create(2026, 3, 1));
        $notify->rentReminders($owner->fresh(), 3);
        $this->assertFalse(OutboundMessage::query()->where('type', 'rent.due_soon')->exists());

        Carbon::setTestNow(Carbon::create(2026, 3, 2));
        $notify->rentReminders($owner->fresh(), 3);
        $this->assertTrue(OutboundMessage::query()->where('type', 'rent.due_soon')->exists());
    }

    public function test_overdue_chasing_is_weekly_not_daily(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1));
        $owner = User::factory()->create();
        $this->letUnit($owner, '2026-03-01');
        $this->postJson('/api/invoices/generate')->assertCreated();

        $notify = app(NotificationService::class);

        // Three consecutive days late should produce one chase, not three.
        foreach ([6, 7, 8] as $day) {
            Carbon::setTestNow(Carbon::create(2026, 3, $day));
            $notify->rentReminders($owner->fresh());
        }
        $this->assertSame(1, OutboundMessage::query()->where('type', 'rent.overdue')->count());

        // A week later, chase again.
        Carbon::setTestNow(Carbon::create(2026, 3, 13));
        $notify->rentReminders($owner->fresh());
        $this->assertSame(2, OutboundMessage::query()->where('type', 'rent.overdue')->count());
    }

    public function test_lease_expiry_warns_the_landlord_once_per_milestone(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 1, 1));
        $owner = User::factory()->create();
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create();
        $tenant = Tenant::factory()->for($owner, 'landlord')->create();

        Sanctum::actingAs($owner);
        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-02',
        ])->assertCreated();

        $notify = app(NotificationService::class);

        Carbon::setTestNow(Carbon::create(2026, 1, 31)); // 30 days left
        $notify->leaseExpiryWarnings($owner->fresh());
        $notify->leaseExpiryWarnings($owner->fresh());

        $this->assertSame(1, Alert::query()->where('type', 'lease.expiring')->count());
    }

    public function test_the_bell_reports_unread_and_can_be_cleared(): void
    {
        $owner = User::factory()->create();
        $this->letUnit($owner);
        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');
        $this->postJson("/api/invoices/{$invoiceId}/payments", ['amount' => 1000, 'method' => 'cash'])
            ->assertCreated();

        $this->getJson('/api/alerts')->assertOk()->assertJsonPath('unread', 1);
        $this->postJson('/api/alerts/read-all')->assertOk();
        $this->getJson('/api/alerts')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_queued_messages_are_dispatched_and_marked_sent(): void
    {
        $owner = User::factory()->create();
        $this->letUnit($owner);
        $this->postJson('/api/invoices/generate')->assertCreated();

        $result = app(NotificationService::class)->dispatchQueued();

        $this->assertGreaterThan(0, $result['sent']);
        $this->assertSame(0, OutboundMessage::query()->where('status', 'queued')->count());
    }

    public function test_alerts_never_cross_accounts(): void
    {
        $mine = User::factory()->create();
        $theirs = User::factory()->create();

        Alert::create([
            'account_owner_id' => $theirs->id,
            'type' => 'payment.received',
            'title' => 'Not yours',
            'body' => 'Not yours',
        ]);

        Sanctum::actingAs($mine);
        $this->getJson('/api/alerts')->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'data');
    }
}
