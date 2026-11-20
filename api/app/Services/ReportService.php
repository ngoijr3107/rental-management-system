<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\UnitStatus;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Reporting reads across the whole portfolio. Aggregation is done in PHP
 * rather than SQL on purpose: month bucketing differs between MySQL
 * (DATE_FORMAT) and SQLite (strftime), and a landlord's data volume is
 * small enough that portability is worth more than the query optimisation.
 */
class ReportService
{
    /** Whole days between two dates, sign-aware and free of Carbon version quirks. */
    private function daysBetween(Carbon $from, Carbon $to): int
    {
        $a = $from->copy()->startOfDay()->getTimestamp();
        $b = $to->copy()->startOfDay()->getTimestamp();

        return (int) floor(($b - $a) / 86400);
    }

    private function money(float $value): float
    {
        return round($value, 2);
    }

    /** Outstanding balance across a collection of invoices. */
    private function balanceOf($invoices): float
    {
        return $this->money(
            $invoices->sum(fn (Invoice $invoice) => (float) $invoice->amount - (float) $invoice->amount_paid)
        );
    }

    // -----------------------------------------------------------------
    // 1. Rent roll — contracted income, unit by unit
    // -----------------------------------------------------------------
    public function rentRoll(User $user): array
    {
        $properties = Property::query()
            ->ownedBy($user)
            ->with(['units.activeLease.tenant', 'units.activeLease.invoices'])
            ->orderBy('name')
            ->get();

        $groups = [];
        $contracted = 0.0;
        $marketVacant = 0.0;
        $totalUnits = 0;
        $occupiedUnits = 0;
        $arrears = 0.0;

        foreach ($properties as $property) {
            $rows = [];
            $propertyContracted = 0.0;

            foreach ($property->units->sortBy('label') as $unit) {
                $lease = $unit->activeLease;
                $rent = $lease ? (float) $lease->rent_amount : 0.0;
                $balance = $lease ? $this->balanceOf($lease->invoices->filter(fn ($i) => $i->status->value !== 'cancelled')) : 0.0;

                $rows[] = [
                    'unit_id' => $unit->id,
                    'unit' => $unit->label,
                    'status' => $unit->status->value,
                    'tenant' => $lease?->tenant?->full_name,
                    'phone' => $lease?->tenant?->phone,
                    'lease_id' => $lease?->id,
                    'lease_start' => $lease?->start_date?->toDateString(),
                    'lease_end' => $lease?->end_date?->toDateString(),
                    'market_rent' => $this->money((float) $unit->rent_amount),
                    'contracted_rent' => $this->money($rent),
                    'deposit_held' => $lease ? $this->money((float) $lease->deposit_amount) : 0.0,
                    'balance' => $this->money($balance),
                ];

                $totalUnits++;
                $propertyContracted += $rent;
                $arrears += $balance;

                if ($lease) {
                    $occupiedUnits++;
                } else {
                    $marketVacant += (float) $unit->rent_amount;
                }
            }

            $groups[] = [
                'property_id' => $property->id,
                'property' => $property->name,
                'units' => $rows,
                'contracted_rent' => $this->money($propertyContracted),
            ];

            $contracted += $propertyContracted;
        }

        return [
            'generated_at' => now()->toDateTimeString(),
            'groups' => $groups,
            'totals' => [
                'units' => $totalUnits,
                'occupied' => $occupiedUnits,
                'vacant' => $totalUnits - $occupiedUnits,
                'occupancy_rate' => $totalUnits ? round($occupiedUnits / $totalUnits * 100, 1) : 0.0,
                'contracted_monthly' => $this->money($contracted),
                'vacancy_loss_monthly' => $this->money($marketVacant),
                'potential_monthly' => $this->money($contracted + $marketVacant),
                'arrears_total' => $this->money($arrears),
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 2. Arrears aging — who owes, and how stale the debt is
    // -----------------------------------------------------------------
    public function arrearsAging(User $user): array
    {
        $invoices = Invoice::query()
            ->ownedBy($user)
            ->outstanding()
            ->with(['lease.tenant', 'lease.unit.property'])
            ->get();

        $today = now();
        $buckets = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0];
        $byTenant = [];

        foreach ($invoices as $invoice) {
            $balance = (float) $invoice->amount - (float) $invoice->amount_paid;
            if ($balance <= 0) {
                continue;
            }

            $daysLate = max(0, $this->daysBetween($invoice->due_date, $today));

            $bucket = match (true) {
                $daysLate <= 0 => 'current',
                $daysLate <= 30 => 'd1_30',
                $daysLate <= 60 => 'd31_60',
                $daysLate <= 90 => 'd61_90',
                default => 'd90_plus',
            };

            $buckets[$bucket] += $balance;

            $tenant = $invoice->lease?->tenant;
            $key = $tenant?->id ?? 0;

            if (! isset($byTenant[$key])) {
                $byTenant[$key] = [
                    'tenant_id' => $tenant?->id,
                    'tenant' => $tenant?->full_name ?? '—',
                    'phone' => $tenant?->phone,
                    'unit' => $invoice->lease?->unit?->label,
                    'property' => $invoice->lease?->unit?->property?->name,
                    'lease_id' => $invoice->lease?->id,
                    'oldest_due' => $invoice->due_date->toDateString(),
                    'days_late' => $daysLate,
                    'invoices' => 0,
                    'current' => 0.0,
                    'd1_30' => 0.0,
                    'd31_60' => 0.0,
                    'd61_90' => 0.0,
                    'd90_plus' => 0.0,
                    'total' => 0.0,
                ];
            }

            $row = &$byTenant[$key];
            $row['invoices']++;
            $row[$bucket] += $balance;
            $row['total'] += $balance;

            if ($invoice->due_date->toDateString() < $row['oldest_due']) {
                $row['oldest_due'] = $invoice->due_date->toDateString();
                $row['days_late'] = $daysLate;
            }
            $row['days_late'] = max($row['days_late'], $daysLate);
            unset($row);
        }

        $rows = collect($byTenant)
            ->map(function (array $row) {
                foreach (['current', 'd1_30', 'd31_60', 'd61_90', 'd90_plus', 'total'] as $key) {
                    $row[$key] = $this->money($row[$key]);
                }

                return $row;
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return [
            'generated_at' => now()->toDateTimeString(),
            'rows' => $rows,
            'buckets' => array_map(fn ($v) => $this->money($v), $buckets),
            'totals' => [
                'debtors' => count($rows),
                'total' => $this->money(array_sum($buckets)),
                'overdue_total' => $this->money(array_sum($buckets) - $buckets['current']),
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 3. Collections trend — billed vs received, month by month
    // -----------------------------------------------------------------
    public function collections(User $user, int $months = 12): array
    {
        $start = now()->copy()->subMonths($months - 1)->startOfMonth();
        $end = now()->copy()->endOfMonth();

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $month = $start->copy()->addMonths($i);
            $series[$month->format('Y-m')] = [
                'month' => $month->format('Y-m'),
                'label' => $month->format('M Y'),
                'billed' => 0.0,
                'collected' => 0.0,
                'invoices' => 0,
            ];
        }

        Invoice::query()
            ->ownedBy($user)
            ->whereDate('period_start', '>=', $start->toDateString())
            ->whereDate('period_start', '<=', $end->toDateString())
            ->get(['id', 'period_start', 'amount', 'status'])
            ->each(function (Invoice $invoice) use (&$series) {
                $key = $invoice->period_start->format('Y-m');
                if (! isset($series[$key]) || $invoice->status->value === 'cancelled') {
                    return;
                }
                $series[$key]['billed'] += (float) $invoice->amount;
                $series[$key]['invoices']++;
            });

        Payment::query()
            ->ownedBy($user)
            ->whereDate('paid_at', '>=', $start->toDateString())
            ->whereDate('paid_at', '<=', $end->toDateString())
            ->get(['id', 'paid_at', 'amount'])
            ->each(function (Payment $payment) use (&$series) {
                $key = $payment->paid_at->format('Y-m');
                if (isset($series[$key])) {
                    $series[$key]['collected'] += (float) $payment->amount;
                }
            });

        $rows = [];
        $totalBilled = 0.0;
        $totalCollected = 0.0;

        foreach ($series as $row) {
            $row['billed'] = $this->money($row['billed']);
            $row['collected'] = $this->money($row['collected']);
            $row['variance'] = $this->money($row['collected'] - $row['billed']);
            $row['rate'] = $row['billed'] > 0 ? round($row['collected'] / $row['billed'] * 100, 1) : null;
            $totalBilled += $row['billed'];
            $totalCollected += $row['collected'];
            $rows[] = $row;
        }

        $recent = array_slice($rows, -3);
        $recentBilled = array_sum(array_column($recent, 'billed'));
        $recentCollected = array_sum(array_column($recent, 'collected'));

        return [
            'generated_at' => now()->toDateTimeString(),
            'months' => $months,
            'rows' => $rows,
            'totals' => [
                'billed' => $this->money($totalBilled),
                'collected' => $this->money($totalCollected),
                'uncollected' => $this->money($totalBilled - $totalCollected),
                'rate' => $totalBilled > 0 ? round($totalCollected / $totalBilled * 100, 1) : null,
                'recent_rate' => $recentBilled > 0 ? round($recentCollected / $recentBilled * 100, 1) : null,
                'best_month' => collect($rows)->whereNotNull('rate')->sortByDesc('rate')->first()['label'] ?? null,
                'worst_month' => collect($rows)->whereNotNull('rate')->sortBy('rate')->first()['label'] ?? null,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 4. Vacancy — empty doors and what they have cost
    // -----------------------------------------------------------------
    public function vacancy(User $user): array
    {
        $units = Unit::query()
            ->ownedBy($user)
            ->where('status', '!=', UnitStatus::Occupied)
            ->with(['property', 'leases.tenant'])
            ->get();

        $today = now();
        $rows = [];
        $lostToDate = 0.0;
        $monthlyLoss = 0.0;

        foreach ($units as $unit) {
            $lastLease = $unit->leases
                ->filter(fn (Lease $lease) => $lease->status !== LeaseStatus::Active)
                ->sortByDesc(fn (Lease $lease) => $lease->endedOn()?->timestamp ?? 0)
                ->first();

            $vacantSince = $lastLease?->endedOn() ?? $unit->created_at;
            $days = max(0, $this->daysBetween($vacantSince, $today));
            $rent = (float) $unit->rent_amount;
            $lost = $rent / 30 * $days;

            $rows[] = [
                'unit_id' => $unit->id,
                'unit' => $unit->label,
                'property' => $unit->property?->name,
                'status' => $unit->status->value,
                'bedrooms' => $unit->bedrooms,
                'monthly_rent' => $this->money($rent),
                'vacant_since' => $vacantSince?->toDateString(),
                'days_vacant' => $days,
                'revenue_lost' => $this->money($lost),
                'last_tenant' => $lastLease?->tenant?->full_name,
            ];

            $lostToDate += $lost;
            $monthlyLoss += $rent;
        }

        usort($rows, fn ($a, $b) => $b['days_vacant'] <=> $a['days_vacant']);

        $totalUnits = Unit::query()->ownedBy($user)->count();

        return [
            'generated_at' => now()->toDateTimeString(),
            'rows' => $rows,
            'totals' => [
                'vacant_units' => count($rows),
                'total_units' => $totalUnits,
                'vacancy_rate' => $totalUnits ? round(count($rows) / $totalUnits * 100, 1) : 0.0,
                'monthly_loss' => $this->money($monthlyLoss),
                'revenue_lost_to_date' => $this->money($lostToDate),
                'longest_vacant_days' => $rows[0]['days_vacant'] ?? 0,
                'average_days_vacant' => count($rows)
                    ? (int) round(array_sum(array_column($rows, 'days_vacant')) / count($rows))
                    : 0,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 5. Renewals — leases ending soon, so units get re-let not emptied
    // -----------------------------------------------------------------
    public function leaseExpiries(User $user, int $days = 90): array
    {
        $leases = Lease::query()
            ->ownedBy($user)
            ->active()
            ->with(['tenant', 'unit.property'])
            ->get();

        $today = now();
        $rows = [];
        $openEnded = 0;
        $buckets = ['expired' => 0, 'd0_30' => 0, 'd31_60' => 0, 'd61_90' => 0];
        $rentAtRisk = 0.0;

        foreach ($leases as $lease) {
            if (! $lease->end_date) {
                $openEnded++;
                continue;
            }

            $daysLeft = $this->daysBetween($today, $lease->end_date);

            if ($daysLeft > $days) {
                continue;
            }

            $bucket = match (true) {
                $daysLeft < 0 => 'expired',
                $daysLeft <= 30 => 'd0_30',
                $daysLeft <= 60 => 'd31_60',
                default => 'd61_90',
            };

            $buckets[$bucket]++;
            $rentAtRisk += (float) $lease->rent_amount;

            $rows[] = [
                'lease_id' => $lease->id,
                'tenant' => $lease->tenant?->full_name,
                'phone' => $lease->tenant?->phone,
                'unit' => $lease->unit?->label,
                'property' => $lease->unit?->property?->name,
                'start_date' => $lease->start_date->toDateString(),
                'end_date' => $lease->end_date->toDateString(),
                'days_left' => $daysLeft,
                'bucket' => $bucket,
                'monthly_rent' => $this->money((float) $lease->rent_amount),
                'months_tenanted' => max(0, (int) floor($this->daysBetween($lease->start_date, $today) / 30)),
            ];
        }

        usort($rows, fn ($a, $b) => $a['days_left'] <=> $b['days_left']);

        return [
            'generated_at' => now()->toDateTimeString(),
            'window_days' => $days,
            'rows' => $rows,
            'buckets' => $buckets,
            'totals' => [
                'expiring' => count($rows),
                'open_ended' => $openEnded,
                'monthly_rent_at_risk' => $this->money($rentAtRisk),
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 6. Property performance — which building actually earns
    // -----------------------------------------------------------------
    public function propertyPerformance(User $user, Carbon $from, Carbon $to): array
    {
        $properties = Property::query()
            ->ownedBy($user)
            ->with('units.activeLease')
            ->orderBy('name')
            ->get();

        $unitToProperty = [];
        $rows = [];

        foreach ($properties as $property) {
            foreach ($property->units as $unit) {
                $unitToProperty[$unit->id] = $property->id;
            }

            $units = $property->units;
            $occupied = $units->filter(fn (Unit $u) => $u->activeLease !== null)->count();

            $rows[$property->id] = [
                'property_id' => $property->id,
                'property' => $property->name,
                'units' => $units->count(),
                'occupied' => $occupied,
                'occupancy_rate' => $units->count() ? round($occupied / $units->count() * 100, 1) : 0.0,
                'contracted_monthly' => $this->money(
                    $units->sum(fn (Unit $u) => $u->activeLease ? (float) $u->activeLease->rent_amount : 0.0)
                ),
                'billed' => 0.0,
                'collected' => 0.0,
                'maintenance_cost' => 0.0,
                'maintenance_jobs' => 0,
            ];
        }

        Invoice::query()
            ->ownedBy($user)
            ->whereDate('period_start', '>=', $from->toDateString())
            ->whereDate('period_start', '<=', $to->toDateString())
            ->with('lease:id,unit_id')
            ->get()
            ->each(function (Invoice $invoice) use (&$rows, $unitToProperty) {
                $propertyId = $unitToProperty[$invoice->lease?->unit_id] ?? null;
                if ($propertyId && $invoice->status->value !== 'cancelled') {
                    $rows[$propertyId]['billed'] += (float) $invoice->amount;
                }
            });

        Payment::query()
            ->ownedBy($user)
            ->whereDate('paid_at', '>=', $from->toDateString())
            ->whereDate('paid_at', '<=', $to->toDateString())
            ->with('invoice.lease:id,unit_id')
            ->get()
            ->each(function (Payment $payment) use (&$rows, $unitToProperty) {
                $propertyId = $unitToProperty[$payment->invoice?->lease?->unit_id] ?? null;
                if ($propertyId) {
                    $rows[$propertyId]['collected'] += (float) $payment->amount;
                }
            });

        MaintenanceRequest::query()
            ->ownedBy($user)
            ->whereDate('reported_at', '>=', $from->toDateString())
            ->whereDate('reported_at', '<=', $to->toDateString())
            ->get(['id', 'unit_id', 'cost'])
            ->each(function (MaintenanceRequest $job) use (&$rows, $unitToProperty) {
                $propertyId = $unitToProperty[$job->unit_id] ?? null;
                if ($propertyId) {
                    $rows[$propertyId]['maintenance_cost'] += (float) ($job->cost ?? 0);
                    $rows[$propertyId]['maintenance_jobs']++;
                }
            });

        $final = [];
        foreach ($rows as $row) {
            $row['billed'] = $this->money($row['billed']);
            $row['collected'] = $this->money($row['collected']);
            $row['maintenance_cost'] = $this->money($row['maintenance_cost']);
            $row['uncollected'] = $this->money($row['billed'] - $row['collected']);
            $row['net'] = $this->money($row['collected'] - $row['maintenance_cost']);
            $row['collection_rate'] = $row['billed'] > 0
                ? round($row['collected'] / $row['billed'] * 100, 1)
                : null;
            $row['maintenance_ratio'] = $row['collected'] > 0
                ? round($row['maintenance_cost'] / $row['collected'] * 100, 1)
                : null;
            $final[] = $row;
        }

        usort($final, fn ($a, $b) => $b['net'] <=> $a['net']);

        return [
            'generated_at' => now()->toDateTimeString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'rows' => $final,
            'totals' => [
                'billed' => $this->money(array_sum(array_column($final, 'billed'))),
                'collected' => $this->money(array_sum(array_column($final, 'collected'))),
                'maintenance_cost' => $this->money(array_sum(array_column($final, 'maintenance_cost'))),
                'net' => $this->money(array_sum(array_column($final, 'net'))),
                'best' => $final[0]['property'] ?? null,
                'weakest' => $final ? $final[count($final) - 1]['property'] : null,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 7. Maintenance spend — what upkeep costs, and where it concentrates
    // -----------------------------------------------------------------
    public function maintenanceSpend(User $user, Carbon $from, Carbon $to): array
    {
        $jobs = MaintenanceRequest::query()
            ->ownedBy($user)
            ->whereDate('reported_at', '>=', $from->toDateString())
            ->whereDate('reported_at', '<=', $to->toDateString())
            ->with('unit.property')
            ->get();

        $byProperty = [];
        $byPriority = ['low' => ['jobs' => 0, 'cost' => 0.0], 'medium' => ['jobs' => 0, 'cost' => 0.0],
            'high' => ['jobs' => 0, 'cost' => 0.0], 'urgent' => ['jobs' => 0, 'cost' => 0.0]];
        $byUnit = [];
        $resolutionDays = [];
        $open = 0;

        foreach ($jobs as $job) {
            $cost = (float) ($job->cost ?? 0);
            $propertyName = $job->unit?->property?->name ?? '—';

            $byProperty[$propertyName] ??= ['property' => $propertyName, 'jobs' => 0, 'cost' => 0.0, 'open' => 0];
            $byProperty[$propertyName]['jobs']++;
            $byProperty[$propertyName]['cost'] += $cost;

            $priority = $job->priority->value;
            $byPriority[$priority]['jobs']++;
            $byPriority[$priority]['cost'] += $cost;

            $unitKey = $job->unit_id;
            $byUnit[$unitKey] ??= [
                'unit_id' => $job->unit_id,
                'unit' => $job->unit?->label ?? '—',
                'property' => $propertyName,
                'jobs' => 0,
                'cost' => 0.0,
            ];
            $byUnit[$unitKey]['jobs']++;
            $byUnit[$unitKey]['cost'] += $cost;

            if (in_array($job->status, [MaintenanceStatus::Open, MaintenanceStatus::InProgress], true)) {
                $open++;
                $byProperty[$propertyName]['open']++;
            }

            if ($job->resolved_at && $job->reported_at) {
                $resolutionDays[] = max(0, $this->daysBetween($job->reported_at, $job->resolved_at));
            }
        }

        $properties = collect($byProperty)
            ->map(fn ($row) => [...$row, 'cost' => $this->money($row['cost'])])
            ->sortByDesc('cost')
            ->values()
            ->all();

        $units = collect($byUnit)
            ->map(fn ($row) => [...$row, 'cost' => $this->money($row['cost'])])
            ->sortByDesc('cost')
            ->take(10)
            ->values()
            ->all();

        return [
            'generated_at' => now()->toDateTimeString(),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'properties' => $properties,
            'priorities' => collect($byPriority)
                ->map(fn ($row, $key) => ['priority' => $key, 'jobs' => $row['jobs'], 'cost' => $this->money($row['cost'])])
                ->values()
                ->all(),
            'worst_units' => $units,
            'totals' => [
                'jobs' => $jobs->count(),
                'open' => $open,
                'cost' => $this->money($jobs->sum(fn ($j) => (float) ($j->cost ?? 0))),
                'average_cost' => $jobs->count()
                    ? $this->money($jobs->sum(fn ($j) => (float) ($j->cost ?? 0)) / $jobs->count())
                    : 0.0,
                'average_resolution_days' => count($resolutionDays)
                    ? (int) round(array_sum($resolutionDays) / count($resolutionDays))
                    : null,
            ],
        ];
    }

    // -----------------------------------------------------------------
    // 8. Tenant statement — a printable ledger for one tenant
    // -----------------------------------------------------------------
    public function tenantStatement(User $user, int $tenantId): array
    {
        $tenant = Tenant::query()
            ->ownedBy($user)
            ->with(['leases.unit.property'])
            ->findOrFail($tenantId);

        $leaseIds = $tenant->leases->pluck('id');

        $invoices = Invoice::query()
            ->whereIn('lease_id', $leaseIds)
            ->with('payments')
            ->orderBy('period_start')
            ->get();

        $entries = [];

        foreach ($invoices as $invoice) {
            if ($invoice->status->value === 'cancelled') {
                continue;
            }

            $entries[] = [
                'date' => $invoice->period_start->toDateString(),
                'type' => 'charge',
                'reference' => $invoice->invoice_number,
                'description' => $invoice->period_start->format('M Y'),
                'charge' => $this->money((float) $invoice->amount),
                'credit' => 0.0,
                'invoice_id' => $invoice->id,
            ];

            foreach ($invoice->payments as $payment) {
                $entries[] = [
                    'date' => $payment->paid_at->toDateString(),
                    'type' => 'payment',
                    'reference' => $payment->reference ?: $invoice->invoice_number,
                    'description' => $payment->method->value,
                    'charge' => 0.0,
                    'credit' => $this->money((float) $payment->amount),
                    'invoice_id' => $invoice->id,
                ];
            }
        }

        usort($entries, fn ($a, $b) => [$a['date'], $a['type']] <=> [$b['date'], $b['type']]);

        $running = 0.0;
        foreach ($entries as $i => $entry) {
            $running += $entry['charge'] - $entry['credit'];
            $entries[$i]['balance'] = $this->money($running);
        }

        $activeLease = $tenant->leases->firstWhere('status', LeaseStatus::Active);

        return [
            'generated_at' => now()->toDateTimeString(),
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->full_name,
                'phone' => $tenant->phone,
                'email' => $tenant->email,
            ],
            'lease' => $activeLease ? [
                'id' => $activeLease->id,
                'unit' => $activeLease->unit?->label,
                'property' => $activeLease->unit?->property?->name,
                'rent' => $this->money((float) $activeLease->rent_amount),
                'deposit' => $this->money((float) $activeLease->deposit_amount),
                'start_date' => $activeLease->start_date->toDateString(),
                'end_date' => $activeLease->end_date?->toDateString(),
            ] : null,
            'entries' => $entries,
            'totals' => [
                'charged' => $this->money(array_sum(array_column($entries, 'charge'))),
                'paid' => $this->money(array_sum(array_column($entries, 'credit'))),
                'balance' => $this->money($running),
            ],
        ];
    }
}
