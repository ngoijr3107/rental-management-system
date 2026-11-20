<?php

namespace App\Console\Commands;

use App\Models\AccountSetting;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class GenerateMonthlyInvoices extends Command
{
    protected $signature = 'invoices:generate
                            {--month= : Bill one specific month instead of everything due}
                            {--user= : Only bill the leases of a single landlord (user id)}';

    protected $description = 'Raise rent invoices for every billing period that has begun';

    public function handle(InvoiceService $invoices): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : null;

        // Only account owners hold a portfolio; staff members share theirs.
        $users = User::query()
            ->whereNull('account_owner_id')
            ->when($this->option('user'), fn ($q) => $q->whereKey($this->option('user')))
            ->get();

        $total = 0;

        foreach ($users as $user) {
            // An account can opt out of automatic billing in its settings.
            if (! (AccountSetting::forOwner($user->id)['auto_invoice'] ?? true)) {
                continue;
            }

            // Without --month, bill every period that has started and has no
            // invoice yet. That covers monthly, quarterly, six-month and
            // annual leases from one daily run.
            $created = $month
                ? $invoices->generateForMonth($user, $month)
                : $invoices->generateDue($user);

            $total += $created->count();

            if ($created->isNotEmpty()) {
                $this->line("  {$user->name}: {$created->count()} invoice(s)");
            }
        }

        $label = $month ? $month->format('F Y') : 'all periods now due';
        $this->info("Billed {$label} — {$total} invoice(s) created or already present.");

        return self::SUCCESS;
    }
}
