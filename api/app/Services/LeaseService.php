<?php

namespace App\Services;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Models\Lease;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaseService
{
    /** Open a lease on a unit, guarding against double-letting the same unit. */
    public function create(Unit $unit, array $data): Lease
    {
        return DB::transaction(function () use ($unit, $data) {
            $this->assertUnitIsFree($unit);

            $lease = $unit->leases()->create([
                'tenant_id' => $data['tenant_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'rent_amount' => $data['rent_amount'] ?? $unit->rent_amount,
                'deposit_amount' => $data['deposit_amount'] ?? $unit->deposit_amount,
                'billing_day' => $data['billing_day'] ?? 1,
                'billing_period_months' => $data['billing_period_months'] ?? 1,
                'status' => LeaseStatus::Active,
                'notes' => $data['notes'] ?? null,
            ]);

            $unit->update(['status' => UnitStatus::Occupied]);

            return $lease->load('tenant', 'unit.property');
        });
    }

    /** End a lease early and release the unit back to the vacant pool. */
    public function terminate(Lease $lease, ?string $terminatedAt = null): Lease
    {
        return DB::transaction(function () use ($lease, $terminatedAt) {
            if ($lease->status !== LeaseStatus::Active) {
                throw ValidationException::withMessages([
                    'lease' => 'Only an active lease can be terminated.',
                ]);
            }

            $date = $terminatedAt ? Carbon::parse($terminatedAt) : now();

            if ($date->lt($lease->start_date)) {
                throw ValidationException::withMessages([
                    'terminated_at' => 'The termination date cannot be before the lease start date.',
                ]);
            }

            $lease->update([
                'status' => LeaseStatus::Terminated,
                'terminated_at' => $date->toDateString(),
            ]);

            $lease->unit->update(['status' => UnitStatus::Vacant]);

            return $lease->fresh(['tenant', 'unit.property']);
        });
    }

    /** Flag leases whose end date has passed, and free up their units. */
    public function expireFinishedLeases(): int
    {
        $leases = Lease::query()
            ->active()
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', now()->toDateString())
            ->with('unit')
            ->get();

        foreach ($leases as $lease) {
            $lease->update(['status' => LeaseStatus::Expired]);
            $lease->unit->update(['status' => UnitStatus::Vacant]);
        }

        return $leases->count();
    }

    private function assertUnitIsFree(Unit $unit): void
    {
        $hasActiveLease = $unit->leases()
            ->where('status', LeaseStatus::Active)
            ->exists();

        if ($hasActiveLease) {
            throw ValidationException::withMessages([
                'unit_id' => 'This unit already has an active lease. Terminate it first.',
            ]);
        }
    }
}
