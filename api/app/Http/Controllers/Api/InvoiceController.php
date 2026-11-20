<?php

namespace App\Http\Controllers\Api;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Lease;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly ActivityLogger $log,
        private readonly NotificationService $notify,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $invoices = Invoice::query()
            ->ownedBy($request->user())
            ->with(['lease.tenant', 'lease.unit.property'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('lease_id'), fn ($q) => $q->where('lease_id', $request->lease_id))
            ->when($request->boolean('overdue'), fn ($q) => $q->overdue())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('period_start', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('period_start', '<=', $request->to))
            ->orderByDesc('due_date')
            ->paginate($request->integer('per_page', 15));

        return InvoiceResource::collection($invoices);
    }

    public function show(Request $request, int $invoice): InvoiceResource
    {
        $model = Invoice::query()
            ->ownedBy($request->user())
            ->with(['lease.tenant', 'lease.unit.property', 'payments.recorder'])
            ->findOrFail($invoice);

        return new InvoiceResource($model);
    }

    /**
     * Generate this month's invoices for every active lease.
     * Idempotent — running it twice will not duplicate anything.
     */
    public function generate(Request $request): JsonResponse
    {
        $this->must($request, 'invoices.manage');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();

        $invoices = $this->invoices->generateForMonth($request->user(), $month);

        if ($invoices->isNotEmpty()) {
            $this->log->record(
                $request->user(),
                'invoice.generated',
                "Raised {$invoices->count()} invoice(s) for {$month->format('F Y')}",
                null,
                ['month' => $month->format('Y-m'), 'count' => $invoices->count()]
            );

            // Tell each tenant what they now owe.
            $ownerId = $request->user()->accountOwnerId();
            $invoices->loadMissing('lease.tenant')->each(
                fn ($invoice) => $this->notify->invoiceRaised($ownerId, $invoice)
            );
        }

        return response()->json([
            'message' => "Generated invoices for {$month->format('F Y')}.",
            'count' => $invoices->count(),
            'data' => InvoiceResource::collection($invoices->load('lease.tenant')),
        ], 201);
    }

    /** Generate (or fetch) a single lease's invoice for a month. */
    public function storeForLease(Request $request, int $lease): JsonResponse
    {
        $this->must($request, 'invoices.manage');

        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $model = Lease::query()->ownedBy($request->user())->findOrFail($lease);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : now()->startOfMonth();

        $invoice = $this->invoices->generateForLease($model, $month);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'month' => 'This lease was not running during the selected month.',
            ]);
        }

        return (new InvoiceResource($invoice->load('lease.tenant')))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(Request $request, int $invoice): InvoiceResource
    {
        $this->must($request, 'invoices.manage');

        $model = Invoice::query()->ownedBy($request->user())->findOrFail($invoice);

        if ((float) $model->amount_paid > 0) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice already has payments against it and cannot be cancelled.',
            ]);
        }

        $model->update(['status' => InvoiceStatus::Cancelled]);

        $this->log->record(
            $request->user(),
            'invoice.cancelled',
            "Cancelled invoice {$model->invoice_number}",
            $model
        );

        return new InvoiceResource($model->fresh());
    }
}
