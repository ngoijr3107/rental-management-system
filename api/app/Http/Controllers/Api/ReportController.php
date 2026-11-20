<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(private readonly ReportService $reports)
    {
    }

    /** Contracted income unit by unit, plus what vacancy is costing. */
    public function rentRoll(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        return response()->json($this->reports->rentRoll($request->user()));
    }

    /** Debt by tenant, bucketed by how long it has been outstanding. */
    public function arrears(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        return response()->json($this->reports->arrearsAging($request->user()));
    }

    /** Billed vs received per month, with the collection rate. */
    public function collections(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        $validated = $request->validate([
            'months' => ['nullable', 'integer', 'min:3', 'max:36'],
        ]);

        return response()->json(
            $this->reports->collections($request->user(), $validated['months'] ?? 12)
        );
    }

    /** Empty units, how long they have been empty, and the revenue forgone. */
    public function vacancy(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        return response()->json($this->reports->vacancy($request->user()));
    }

    /** Leases ending inside the window, so units get re-let rather than emptied. */
    public function renewals(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:7', 'max:365'],
        ]);

        return response()->json(
            $this->reports->leaseExpiries($request->user(), $validated['days'] ?? 90)
        );
    }

    /** Per-property income against upkeep cost for a period. */
    public function propertyPerformance(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        [$from, $to] = $this->period($request);

        return response()->json(
            $this->reports->propertyPerformance($request->user(), $from, $to)
        );
    }

    /** Upkeep spend by property, priority, and the units that keep breaking. */
    public function maintenance(Request $request): JsonResponse
    {
        $this->must($request, 'reports.view');

        [$from, $to] = $this->period($request);

        return response()->json(
            $this->reports->maintenanceSpend($request->user(), $from, $to)
        );
    }

    /** A printable ledger of charges and payments for one tenant. */
    public function tenantStatement(Request $request, int $tenant): JsonResponse
    {
        $this->must($request, 'reports.view');

        return response()->json(
            $this->reports->tenantStatement($request->user(), $tenant)
        );
    }

    /** Shared date-range handling: defaults to the last twelve months. */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : now()->copy()->subMonths(11)->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : now()->copy()->endOfMonth();

        return [$from, $to];
    }
}
