<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaseStatus;
use App\Enums\UnitStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** A single call that gives a client everything it needs for a home screen. */
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $totalUnits = Unit::query()->ownedBy($user)->count();
        $occupiedUnits = Unit::query()->ownedBy($user)->where('status', UnitStatus::Occupied)->count();

        $outstanding = (float) Invoice::query()
            ->ownedBy($user)
            ->outstanding()
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        $overdue = Invoice::query()->ownedBy($user)->overdue();

        $overdueAmount = (float) (clone $overdue)
            ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
            ->value('total');

        return response()->json([
            'portfolio' => [
                'properties' => Property::query()->ownedBy($user)->count(),
                'units' => $totalUnits,
                'occupied_units' => $occupiedUnits,
                'vacant_units' => Unit::query()->ownedBy($user)->where('status', UnitStatus::Vacant)->count(),
                'occupancy_rate' => $totalUnits > 0 ? round($occupiedUnits / $totalUnits * 100, 1) : 0.0,
                'tenants' => Tenant::query()->ownedBy($user)->count(),
                'active_leases' => Lease::query()->ownedBy($user)->where('status', LeaseStatus::Active)->count(),
            ],
            'finance' => [
                'billed_this_month' => (float) Invoice::query()
                    ->ownedBy($user)
                    ->whereBetween('period_start', [$monthStart, $monthEnd])
                    ->sum('amount'),
                'collected_this_month' => (float) Payment::query()
                    ->ownedBy($user)
                    ->whereBetween('paid_at', [$monthStart, $monthEnd])
                    ->sum('amount'),
                'outstanding_total' => round($outstanding, 2),
                'overdue_total' => round($overdueAmount, 2),
                'overdue_invoices' => (clone $overdue)->count(),
            ],
            'recent_payments' => PaymentResource::collection(
                Payment::query()
                    ->ownedBy($user)
                    ->with('invoice.lease.tenant')
                    ->latest('paid_at')
                    ->limit(5)
                    ->get()
            ),
            'overdue_invoices' => InvoiceResource::collection(
                Invoice::query()
                    ->ownedBy($user)
                    ->overdue()
                    ->with('lease.tenant', 'lease.unit')
                    ->orderBy('due_date')
                    ->limit(5)
                    ->get()
            ),
        ]);
    }
}
