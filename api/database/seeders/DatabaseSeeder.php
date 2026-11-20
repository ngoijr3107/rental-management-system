<?php

namespace Database\Seeders;

use App\Enums\PaymentMethod;
use App\Enums\PropertyType;
use App\Enums\Role;
use App\Models\AccountSetting;
use App\Models\MaintenanceRequest;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\LeaseService;
use App\Services\PaymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    /**
     * Builds a realistic demo portfolio: two properties, seven units, five
     * tenants on active leases, three months of invoices and part-paid rent —
     * enough for a client app to have something to render immediately.
     */
    public function run(InvoiceService $invoices, LeaseService $leases, PaymentService $payments): void
    {
        $landlord = User::updateOrCreate(
            ['email' => 'admin@twigapremier.com'],
            [
                'name' => 'Admin',
                'phone' => '+255700000000',
                'password' => 'password',
                'role' => Role::Owner->value,
                'is_active' => true,
            ]
        );

        AccountSetting::put($landlord->id, [
            'business_name' => 'Twiga Premier',
            'business_email' => 'admin@twigapremier.com',
            'invoice_prefix' => 'INV',
        ]);

        // A small team so the admin screens have something to show.
        foreach ([
            ['name' => 'Manager', 'email' => 'manager@twigapremier.com', 'role' => Role::Manager],
            ['name' => 'Agent', 'email' => 'agent@twigapremier.com', 'role' => Role::Agent],
            ['name' => 'Accountant', 'email' => 'accountant@twigapremier.com', 'role' => Role::Accountant],
            ['name' => 'Partner', 'email' => 'viewer@twigapremier.com', 'role' => Role::Viewer],
        ] as $member) {
            User::updateOrCreate(
                ['email' => $member['email']],
                [
                    'account_owner_id' => $landlord->id,
                    'name' => $member['name'],
                    'role' => $member['role']->value,
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
        }

        // $riverside = Property::factory()->create([
        //     'user_id' => $landlord->id,
        //     'name' => 'Riverside Apartments',
        //     'type' => PropertyType::Apartment,
        //     'address' => '12 Riverside Drive',
        // ]);

        // $palm = Property::factory()->create([
        //     'user_id' => $landlord->id,
        //     'name' => 'Palm Court Houses',
        //     'type' => PropertyType::House,
        //     'address' => '4 Palm Court',
        // ]);

        // $units = collect();

        // foreach (['A1', 'A2', 'A3', 'A4'] as $i => $label) {
        //     $units->push(Unit::factory()->create([
        //         'property_id' => $riverside->id,
        //         'label' => $label,
        //         'bedrooms' => 1 + ($i % 3),
        //         'bathrooms' => 1,
        //         'rent_amount' => 350_000 + ($i * 50_000),
        //         'deposit_amount' => 350_000 + ($i * 50_000),
        //     ]));
        // }

        // foreach (['H1', 'H2', 'H3'] as $i => $label) {
        //     $units->push(Unit::factory()->create([
        //         'property_id' => $palm->id,
        //         'label' => $label,
        //         'bedrooms' => 3,
        //         'bathrooms' => 2,
        //         'rent_amount' => 900_000,
        //         'deposit_amount' => 1_800_000,
        //     ]));
        // }

        // $tenants = Tenant::factory()->count(5)->create(['user_id' => $landlord->id]);

        // // Let five of the seven units, leaving two vacant on purpose.
        // $start = Carbon::now()->subMonths(2)->startOfMonth();

        // $tenants->each(function (Tenant $tenant, int $i) use ($units, $leases, $start) {
        //     $leases->create($units[$i], [
        //         'tenant_id' => $tenant->id,
        //         'start_date' => $start->copy()->addDays($i * 3)->toDateString(),
        //         'end_date' => $start->copy()->addYear()->toDateString(),
        //         'billing_day' => 5,
        //     ]);
        // });

        // // Three months of billing, most of it settled.
        // foreach ([2, 1, 0] as $offset) {
        //     $month = Carbon::now()->subMonths($offset)->startOfMonth();
        //     $generated = $invoices->generateForMonth($landlord, $month);

        //     foreach ($generated as $index => $invoice) {
        //         if ($offset === 0 && $index % 2 === 0) {
        //             continue; // Leave some of this month's rent unpaid.
        //         }

        //         $amount = $offset === 0 && $index % 3 === 0
        //             ? round($invoice->balance / 2, 2)   // a part payment
        //             : $invoice->balance;

        //         if ($amount <= 0) {
        //             continue;
        //         }

        //         $paidAt = $invoice->due_date->copy()->addDay();

        //         $payments->record($invoice, [
        //             'amount' => $amount,
        //             'paid_at' => $paidAt->isFuture() ? now()->toDateString() : $paidAt->toDateString(),
        //             'method' => PaymentMethod::MobileMoney->value,
        //             'reference' => 'SEED-'.$invoice->invoice_number,
        //         ], $landlord);
        //     }
        // }

        // MaintenanceRequest::factory()->count(4)->create([
        //     'unit_id' => $units->first()->id,
        //     'tenant_id' => $tenants->first()->id,
        // ]);

        $this->command?->info('Demo login: admin@twigapremier.com / password');
    }
}
