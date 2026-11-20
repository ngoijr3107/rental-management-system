<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\OutboundMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Two audiences, two mechanisms.
 *
 * Landlords and staff use the app, so they get in-app alerts. Tenants do not
 * have logins, so anything for them has to leave the system as SMS or email.
 * Outbound messages are written to a table first and dispatched separately,
 * which means nothing is lost if a gateway is down, and a landlord can see
 * exactly what was sent and when.
 */
class NotificationService
{
    /** Raise an alert for the landlord, ignoring exact repeats. */
    public function alert(
        int $ownerId,
        string $type,
        string $title,
        string $body,
        ?string $link = null,
        string $severity = 'info',
        ?string $dedupeKey = null
    ): ?Alert {
        if ($dedupeKey && Alert::query()
            ->where('account_owner_id', $ownerId)
            ->where('dedupe_key', $dedupeKey)
            ->exists()
        ) {
            return null;
        }

        return Alert::create([
            'account_owner_id' => $ownerId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'link' => $link,
            'severity' => $severity,
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /** Queue a message to a tenant. Prefers SMS, since phone is always present. */
    public function toTenant(
        int $ownerId,
        Tenant $tenant,
        string $type,
        string $body,
        ?string $dedupeKey = null
    ): ?OutboundMessage {
        $channel = $tenant->phone ? 'sms' : ($tenant->email ? 'email' : null);

        if (! $channel) {
            return null;
        }

        if ($dedupeKey && OutboundMessage::query()
            ->where('account_owner_id', $ownerId)
            ->where('dedupe_key', $dedupeKey)
            ->exists()
        ) {
            return null;
        }

        return OutboundMessage::create([
            'account_owner_id' => $ownerId,
            'tenant_id' => $tenant->id,
            'channel' => $channel,
            'recipient' => $channel === 'sms' ? $tenant->phone : $tenant->email,
            'type' => $type,
            'body' => $body,
            'status' => 'queued',
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /* ---------------------------------------------------------------
     | Events worth telling someone about
     * --------------------------------------------------------------- */

    public function paymentReceived(User $actor, Invoice $invoice, float $amount): void
    {
        $tenant = $invoice->lease?->tenant;
        $money = number_format($amount, 0);

        $this->alert(
            $actor->accountOwnerId(),
            'payment.received',
            'Payment received',
            trim(($tenant?->full_name ?? 'A tenant')." paid {$money} against {$invoice->invoice_number}."),
            "/invoices/{$invoice->id}",
            'success'
        );

        if ($tenant) {
            $balance = number_format(max(0, (float) $invoice->amount - (float) $invoice->amount_paid), 0);
            $this->toTenant(
                $actor->accountOwnerId(),
                $tenant,
                'payment.receipt',
                "Thank you. We have received {$money} for {$invoice->invoice_number}. Balance: {$balance}.",
                "receipt:{$invoice->id}:".now()->timestamp
            );
        }
    }

    public function invoiceRaised(int $ownerId, Invoice $invoice): void
    {
        $tenant = $invoice->lease?->tenant;
        if (! $tenant) {
            return;
        }

        $amount = number_format((float) $invoice->amount, 0);
        $due = $invoice->due_date->format('d M Y');

        $this->toTenant(
            $ownerId,
            $tenant,
            'invoice.raised',
            "Rent invoice {$invoice->invoice_number} for {$amount} is due on {$due}.",
            "invoice:{$invoice->id}"
        );
    }

    /**
     * Rent falling due, and rent already late. Both are deduped per invoice
     * per stage, so a daily run does not nag every morning.
     */
    public function rentReminders(User $owner, int $daysBefore = 3): int
    {
        $today = now()->startOfDay();
        $sent = 0;

        $invoices = Invoice::query()
            ->ownedBy($owner)
            ->outstanding()
            ->with('lease.tenant', 'lease.unit')
            ->get();

        foreach ($invoices as $invoice) {
            $tenant = $invoice->lease?->tenant;
            if (! $tenant) {
                continue;
            }

            $balance = (float) $invoice->amount - (float) $invoice->amount_paid;
            if ($balance <= 0) {
                continue;
            }

            $money = number_format($balance, 0);
            $daysLeft = (int) floor(($invoice->due_date->copy()->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);

            if ($daysLeft === $daysBefore) {
                $sent += $this->toTenant(
                    $owner->accountOwnerId(),
                    $tenant,
                    'rent.due_soon',
                    "Reminder: rent of {$money} is due on {$invoice->due_date->format('d M Y')}.",
                    "due:{$invoice->id}"
                ) ? 1 : 0;
            }

            if ($daysLeft < 0) {
                $late = abs($daysLeft);
                // Chase weekly rather than daily.
                $stage = (int) floor($late / 7);

                $sent += $this->toTenant(
                    $owner->accountOwnerId(),
                    $tenant,
                    'rent.overdue',
                    "Rent of {$money} for {$invoice->invoice_number} is {$late} day(s) overdue. Please settle it.",
                    "overdue:{$invoice->id}:{$stage}"
                ) ? 1 : 0;

                if ($late === 1 || $late === 7 || $late === 30) {
                    $this->alert(
                        $owner->accountOwnerId(),
                        'rent.overdue',
                        'Rent overdue',
                        "{$tenant->full_name} — {$money} is {$late} day(s) late on {$invoice->lease?->unit?->label}.",
                        "/invoices/{$invoice->id}",
                        'danger',
                        "overdue-alert:{$invoice->id}:{$late}"
                    );
                }
            }
        }

        return $sent;
    }

    /** Warn the landlord before a lease runs out, while it can still be renewed. */
    public function leaseExpiryWarnings(User $owner, array $atDays = [60, 30, 7]): int
    {
        $today = now()->startOfDay();
        $raised = 0;

        Lease::query()
            ->ownedBy($owner)
            ->active()
            ->whereNotNull('end_date')
            ->with('tenant', 'unit')
            ->get()
            ->each(function (Lease $lease) use ($today, $atDays, $owner, &$raised) {
                $daysLeft = (int) floor(($lease->end_date->copy()->startOfDay()->getTimestamp() - $today->getTimestamp()) / 86400);

                if (! in_array($daysLeft, $atDays, true)) {
                    return;
                }

                $created = $this->alert(
                    $owner->accountOwnerId(),
                    'lease.expiring',
                    'Lease ending soon',
                    "{$lease->tenant?->full_name} on {$lease->unit?->label} ends in {$daysLeft} day(s).",
                    "/leases/{$lease->id}",
                    'warning',
                    "expiring:{$lease->id}:{$daysLeft}"
                );

                if ($created) {
                    $raised++;
                }
            });

        return $raised;
    }

    /* ---------------------------------------------------------------
     | Delivery
     * --------------------------------------------------------------- */

    /**
     * Hand queued messages to whichever gateway is configured.
     *
     * The default driver writes to the log, so the whole flow is testable and
     * visible before an SMS contract exists. Swapping in a real gateway means
     * implementing one method, not rewiring the app.
     */
    public function dispatchQueued(int $limit = 100): array
    {
        $driver = config('services.sms.driver', 'log');
        $sent = 0;
        $failed = 0;

        OutboundMessage::query()
            ->queued()
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (OutboundMessage $message) use ($driver, &$sent, &$failed) {
                try {
                    if ($driver === 'log') {
                        Log::channel(config('logging.default'))->info('[outbound]', [
                            'channel' => $message->channel,
                            'to' => $message->recipient,
                            'body' => $message->body,
                        ]);
                    } else {
                        // A real gateway goes here. Left unimplemented on
                        // purpose rather than guessing at someone's provider.
                        throw new \RuntimeException("No handler for SMS driver [{$driver}].");
                    }

                    $message->update(['status' => 'sent', 'sent_at' => now(), 'error' => null]);
                    $sent++;
                } catch (\Throwable $e) {
                    $message->update(['status' => 'failed', 'error' => substr($e->getMessage(), 0, 250)]);
                    $failed++;
                }
            });

        return ['sent' => $sent, 'failed' => $failed];
    }
}
