<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\AccountSetting;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class InvoiceService
{
    /**
     * Every billing period this lease runs through, in order.
     *
     * A one-month term bills on calendar months, which is what a tenant paying
     * month to month expects. Any longer term — three months, four, seven,
     * whatever was agreed — runs from the start date instead: a four-month
     * term beginning 16 March covers 16 March to 15 July.
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function periods(Lease $lease, ?Carbon $until = null): array
    {
        $months = $lease->periodMonths();
        $until ??= now();
        $leaseEnd = $lease->endedOn();

        $periods = [];
        $cursor = $lease->start_date->copy();
        $guard = 0;

        while ($guard++ < 600) {
            $start = $cursor->copy();
            // A term runs from its own start date, so a lease beginning on the
            // 2nd covers the 2nd to the 1st of the following month. The tenant
            // agreed a rent per period, not a share of the calendar.
            $naturalEnd = $cursor->copy()->addMonths($months)->subDay();
            $end = ($leaseEnd && $leaseEnd->lt($naturalEnd)) ? $leaseEnd->copy() : $naturalEnd;

            if ($start->gt($until) || ($leaseEnd && $start->gt($leaseEnd))) {
                break;
            }

            $periods[] = [
                'start' => $start,
                'end' => $end,
                // Only a period cut short by the lease ending is a part period.
                'natural_end' => $naturalEnd,
            ];

            $cursor->addMonths($months);
        }

        return $periods;
    }

    /** The period covering a given date, whatever the cycle. */
    public function periodContaining(Lease $lease, Carbon $date): ?array
    {
        foreach ($this->periods($lease, $date->copy()->endOfMonth()) as $period) {
            $start = $period['start']->copy()->startOfDay();
            $end = $period['end']->copy()->endOfDay();

            if ($date->betweenIncluded($start, $end)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * Create (or fetch) the invoice covering $month for a lease. Kept on the
     * same signature so a landlord can still bill one lease for one month by
     * hand; the period is now derived from the lease's cycle.
     */
    public function generateForLease(Lease $lease, Carbon $month, ?string $prefix = null): ?Invoice
    {
        $period = $this->periodContaining($lease, $month->copy()->startOfMonth())
            ?? $this->periodContaining($lease, $month->copy()->endOfMonth());

        if (! $period) {
            return null;
        }

        return $this->createForPeriod($lease, $period, $prefix);
    }

    /** Raise the invoice for one period, or hand back the one already raised. */
    public function createForPeriod(Lease $lease, array $period, ?string $prefix = null): Invoice
    {
        $existing = $lease->invoices()
            ->whereDate('period_start', $period['start']->toDateString())
            ->first();

        if ($existing) {
            return $existing;
        }

        return $lease->invoices()->create([
            'invoice_number' => $this->invoiceNumber($lease, $period['start'], $prefix),
            'period_start' => $period['start'],
            'period_end' => $period['end'],
            'due_date' => $this->dueDate($lease, $period['start']),
            'amount' => $this->amountForPeriod($lease, $period),
            'amount_paid' => 0,
            'status' => InvoiceStatus::Unpaid,
        ]);
    }

    /**
     * Bill every period that has already begun and has no invoice yet.
     * This is what the scheduler calls: a quarterly lease is billed once
     * every three months without anyone choosing a month.
     *
     * @return Collection<int, Invoice>
     */
    public function generateDue(User $user, ?Carbon $asOf = null): Collection
    {
        $asOf ??= now();
        $prefix = AccountSetting::forOwner($user->accountOwnerId())['invoice_prefix'] ?? 'INV';

        $raised = collect();

        Lease::query()
            ->active()
            ->ownedBy($user)
            ->get()
            ->each(function (Lease $lease) use ($asOf, $prefix, $raised) {
                foreach ($this->periods($lease, $asOf) as $period) {
                    $raised->push($this->createForPeriod($lease, $period, $prefix));
                }
            });

        return $raised->values();
    }

    /**
     * Generate invoices for every active lease of a landlord for a given month.
     * Safe to run repeatedly — existing invoices are returned untouched.
     *
     * @return Collection<int, Invoice>
     */
    public function generateForMonth(User $user, Carbon $month): Collection
    {
        $prefix = AccountSetting::forOwner($user->accountOwnerId())['invoice_prefix'] ?? 'INV';

        $leases = Lease::query()
            ->active()
            ->ownedBy($user)
            ->get();

        return $leases
            ->map(fn (Lease $lease) => $this->generateForLease($lease, $month, $prefix))
            ->filter()
            ->values();
    }

    /**
     * A full period costs the monthly rent times the number of months. A partial
     * one — the first period of a mid-month lease, or the last before it ends
     * — is prorated on the days actually covered.
     */
    /**
     * A period costs the agreed rent times the number of months in the term.
     * The only time it costs less is when the lease ends part-way through —
     * a tenant who leaves mid-period is not charged for the whole of it.
     * Starting mid-month is not a part period: the term simply runs from
     * that date.
     */
    private function amountForPeriod(Lease $lease, array $period): float
    {
        $full = $lease->periodAmount();

        $naturalEnd = $period['natural_end'] ?? $period['end'];

        // Same day, or later: the period ran its full length.
        if (! $period['end']->lt($naturalEnd)) {
            return round($full, 2);
        }

        $expectedDays = $this->wholeDays($period['start'], $naturalEnd);
        $actualDays = $this->wholeDays($period['start'], $period['end']);

        if ($expectedDays <= 0 || $actualDays >= $expectedDays) {
            return round($full, 2);
        }

        return round($full * $actualDays / $expectedDays, 2);
    }

    /** Inclusive day count, free of Carbon's version differences. */
    private function wholeDays(Carbon $from, Carbon $to): int
    {
        $a = $from->copy()->startOfDay()->getTimestamp();
        $b = $to->copy()->startOfDay()->getTimestamp();

        return (int) floor(($b - $a) / 86400) + 1;
    }

    /**
     * Rent falls due at the start of the period. An account can allow a few
     * days' grace so a freshly raised invoice is not immediately overdue.
     */
    private function dueDate(Lease $lease, Carbon $periodStart): Carbon
    {
        $grace = (int) (AccountSetting::forOwner($lease->unit?->property?->user_id ?? 0)['grace_days'] ?? 0);

        return $periodStart->copy()->addDays(max(0, $grace));
    }

    private function invoiceNumber(Lease $lease, Carbon $periodStart, ?string $prefix = null): string
    {
        return sprintf('%s-%s-%04d', $prefix ?: 'INV', $periodStart->format('Ym'), $lease->id);
    }
}
