<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\AccountSetting;
use App\Models\ActivityLog;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => Role::Owner->value, 'account_owner_id' => null]);
    }

    private function member(User $owner, Role $role): User
    {
        return User::factory()->create([
            'account_owner_id' => $owner->id,
            'role' => $role->value,
        ]);
    }

    public function test_a_staff_member_sees_the_owners_portfolio(): void
    {
        $owner = $this->owner();
        Property::factory()->for($owner, 'landlord')->create(['name' => 'Riverside']);

        $agent = $this->member($owner, Role::Agent);
        Sanctum::actingAs($agent);

        $this->getJson('/api/properties')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Riverside');
    }

    public function test_an_agent_may_take_money_but_not_reverse_it(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 300000]);
        $tenant = Tenant::factory()->for($owner, 'landlord')->create();

        $agent = $this->member($owner, Role::Agent);
        Sanctum::actingAs($agent);

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');

        $paymentId = $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 100000,
            'method' => 'cash',
        ])->assertCreated()->json('data.id');

        // Reversing money is a manager-and-above action.
        $this->deleteJson("/api/payments/{$paymentId}")->assertForbidden();
    }

    public function test_an_agent_cannot_touch_properties_and_a_viewer_cannot_write(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->for($owner, 'landlord')->create();

        Sanctum::actingAs($this->member($owner, Role::Agent));
        $this->postJson('/api/properties', [
            'name' => 'New block', 'type' => 'apartment', 'address' => '1 Road',
        ])->assertForbidden();
        $this->deleteJson("/api/properties/{$property->id}")->assertForbidden();

        Sanctum::actingAs($this->member($owner, Role::Viewer));
        $this->getJson('/api/properties')->assertOk();
        $this->postJson('/api/tenants', [
            'first_name' => 'A', 'last_name' => 'B', 'phone' => '0700000001',
        ])->assertForbidden();
    }

    public function test_an_accountant_handles_money_but_not_letting(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 200000]);
        $tenant = Tenant::factory()->for($owner, 'landlord')->create();

        Sanctum::actingAs($owner);
        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();
        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');

        Sanctum::actingAs($this->member($owner, Role::Accountant));

        // Money: yes, including corrections.
        $paymentId = $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 50000, 'method' => 'cash',
        ])->assertCreated()->json('data.id');
        $this->deleteJson("/api/payments/{$paymentId}")->assertOk();
        $this->getJson('/api/reports/arrears')->assertOk();

        // Letting and admin: no.
        $this->postJson('/api/tenants', [
            'first_name' => 'X', 'last_name' => 'Y', 'phone' => '0700000009',
        ])->assertForbidden();
        $this->getJson('/api/team')->assertForbidden();
    }

    public function test_only_an_owner_manages_the_team_and_settings(): void
    {
        $owner = $this->owner();
        $manager = $this->member($owner, Role::Manager);

        Sanctum::actingAs($manager);
        $this->getJson('/api/team')->assertForbidden();
        $this->putJson('/api/settings', ['currency' => 'KES'])->assertForbidden();
        // But a manager still needs to read settings to render amounts.
        $this->getJson('/api/settings')->assertOk();

        Sanctum::actingAs($owner);
        $this->getJson('/api/team')->assertOk()->assertJsonCount(2, 'data');
        $this->putJson('/api/settings', ['currency' => 'KES'])
            ->assertOk()
            ->assertJsonPath('data.currency', 'KES');
    }

    public function test_adding_and_removing_a_member_is_recorded(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $memberId = $this->postJson('/api/team', [
            'name' => 'New Agent',
            'email' => 'new.agent@example.com',
            'role' => 'agent',
            'password' => 'secret12345',
            'password_confirmation' => 'secret12345',
        ])->assertCreated()->json('data.id');

        $this->deleteJson("/api/team/{$memberId}")->assertOk();

        $actions = ActivityLog::query()->pluck('action')->all();
        $this->assertContains('user.added', $actions);
        $this->assertContains('user.removed', $actions);
    }

    public function test_the_owner_cannot_be_removed_or_demoted(): void
    {
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $this->deleteJson("/api/team/{$owner->id}")->assertStatus(422);
        $this->putJson("/api/team/{$owner->id}", ['role' => 'viewer'])->assertStatus(422);
    }

    public function test_a_suspended_member_cannot_sign_in(): void
    {
        $owner = $this->owner();
        $member = $this->member($owner, Role::Manager);
        $member->update(['is_active' => false, 'password' => 'secret12345']);

        $this->postJson('/api/login', [
            'email' => $member->email,
            'password' => 'secret12345',
        ])->assertStatus(422);
    }

    public function test_money_movements_land_in_the_activity_log(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 200000]);
        $tenant = Tenant::factory()->for($owner, 'landlord')->create();

        Sanctum::actingAs($owner);

        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated();
        $invoiceId = $this->getJson('/api/invoices')->json('data.0.id');
        $this->postJson("/api/invoices/{$invoiceId}/payments", [
            'amount' => 50000, 'method' => 'cash',
        ])->assertCreated();

        $response = $this->getJson('/api/activity')->assertOk();
        $actions = collect($response->json('data'))->pluck('action')->all();

        $this->assertContains('lease.started', $actions);
        $this->assertContains('invoice.generated', $actions);
        $this->assertContains('payment.recorded', $actions);
    }

    public function test_the_invoice_prefix_setting_is_applied(): void
    {
        $owner = $this->owner();
        $property = Property::factory()->for($owner, 'landlord')->create();
        $unit = Unit::factory()->for($property)->create(['rent_amount' => 100000]);
        $tenant = Tenant::factory()->for($owner, 'landlord')->create();

        AccountSetting::put($owner->id, ['invoice_prefix' => 'RENT']);

        Sanctum::actingAs($owner);
        $this->postJson('/api/leases', [
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'start_date' => now()->startOfMonth()->toDateString(),
        ])->assertCreated();

        $this->postJson('/api/invoices/generate')->assertCreated();

        $number = $this->getJson('/api/invoices')->json('data.0.invoice_number');
        $this->assertStringStartsWith('RENT-', $number);
    }

    public function test_a_logo_can_be_uploaded_and_replaces_the_previous_file(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $first = $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 400, 400),
        ])->assertOk();

        $firstPath = AccountSetting::forOwner($owner->id)['logo_path'];
        Storage::disk('public')->assertExists($firstPath);
        $this->assertNotNull($first->json('data.logo_url'));

        $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('new-logo.png', 400, 400),
        ])->assertOk();

        // The old file should not be left behind.
        Storage::disk('public')->assertMissing($firstPath);
    }

    public function test_svg_and_oversized_logos_are_rejected(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->owner());

        $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
        ])->assertStatus(422);

        $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->create('huge.png', 3000, 'image/png'),
        ])->assertStatus(422);
    }

    public function test_removing_the_logo_clears_the_file_and_the_setting(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Sanctum::actingAs($owner);

        $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertOk();

        $path = AccountSetting::forOwner($owner->id)['logo_path'];

        $this->deleteJson('/api/settings/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing($path);
    }

    public function test_a_manager_cannot_change_the_logo(): void
    {
        Storage::fake('public');
        $owner = $this->owner();
        Sanctum::actingAs($this->member($owner, Role::Manager));

        $this->postJson('/api/settings/logo', [
            'logo' => UploadedFile::fake()->image('logo.png', 300, 300),
        ])->assertForbidden();
    }

    public function test_anyone_can_edit_their_own_profile_including_a_viewer(): void
    {
        $owner = $this->owner();
        $viewer = $this->member($owner, Role::Viewer);

        Sanctum::actingAs($viewer);

        $this->putJson('/api/profile', [
            'name' => 'Neema Updated',
            'phone' => '+255700999888',
        ])->assertOk()->assertJsonPath('data.name', 'Neema Updated');

        $this->assertSame('Neema Updated', $viewer->fresh()->name);
    }

    public function test_a_member_cannot_promote_themselves_through_the_profile(): void
    {
        $owner = $this->owner();
        $agent = $this->member($owner, Role::Agent);

        Sanctum::actingAs($agent);
        $this->putJson('/api/profile', ['name' => 'Still an agent', 'role' => 'owner'])->assertOk();

        $this->assertSame(Role::Agent, $agent->fresh()->role);
    }

    public function test_changing_a_password_needs_the_current_one(): void
    {
        $owner = $this->owner();
        $owner->update(['password' => 'oldpassword123']);
        Sanctum::actingAs($owner);

        $this->putJson('/api/profile/password', [
            'current_password' => 'wrongpassword',
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertStatus(422);

        $this->putJson('/api/profile/password', [
            'current_password' => 'oldpassword123',
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertOk();

        $this->postJson('/api/login', [
            'email' => $owner->email,
            'password' => 'brandnewpass123',
        ])->assertOk();
    }

    public function test_changing_a_password_signs_out_other_devices(): void
    {
        $owner = $this->owner();
        $owner->update(['password' => 'oldpassword123']);

        // A session left open somewhere else.
        $owner->createToken('other-device');
        $this->assertSame(1, $owner->tokens()->count());

        Sanctum::actingAs($owner);
        $this->putJson('/api/profile/password', [
            'current_password' => 'oldpassword123',
            'password' => 'brandnewpass123',
            'password_confirmation' => 'brandnewpass123',
        ])->assertOk();

        $this->assertSame(0, $owner->fresh()->tokens()->count());
    }

    public function test_the_accountant_role_is_offered_when_adding_a_member(): void
    {
        Sanctum::actingAs($this->owner());

        $assignable = collect($this->getJson('/api/team/roles')->assertOk()->json('data'))
            ->where('assignable', true)
            ->pluck('value');

        $this->assertContains('accountant', $assignable->all());
        $this->assertContains('manager', $assignable->all());
        $this->assertNotContains('owner', $assignable->all());
    }

    public function test_activity_is_never_visible_across_accounts(): void
    {
        $mine = $this->owner();
        $theirs = $this->owner();

        ActivityLog::create([
            'account_owner_id' => $theirs->id,
            'user_id' => $theirs->id,
            'action' => 'payment.recorded',
            'summary' => 'Not yours',
        ]);

        Sanctum::actingAs($mine);
        $this->getJson('/api/activity')->assertOk()->assertJsonCount(0, 'data');
    }
}
