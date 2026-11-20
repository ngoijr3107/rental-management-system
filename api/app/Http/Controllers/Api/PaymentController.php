<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\AuthorizesAbilities;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ActivityLogger;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    use AuthorizesAbilities;

    public function __construct(
        private readonly PaymentService $payments,
        private readonly ActivityLogger $log,
        private readonly NotificationService $notify,
    ) {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = Payment::query()
            ->ownedBy($request->user())
            ->with(['invoice.lease.tenant', 'recorder'])
            ->when($request->filled('invoice_id'), fn ($q) => $q->where('invoice_id', $request->invoice_id))
            ->when($request->filled('method'), fn ($q) => $q->where('method', $request->method))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('paid_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('paid_at', '<=', $request->to))
            ->latest('paid_at')
            ->paginate($request->integer('per_page', 15));

        return PaymentResource::collection($payments);
    }

    /** Record a payment against an invoice. */
    public function store(StorePaymentRequest $request, int $invoice): JsonResponse
    {
        $model = Invoice::query()->ownedBy($request->user())->findOrFail($invoice);

        $payment = $this->payments->record($model, $request->validated(), $request->user());

        $this->log->record(
            $request->user(),
            'payment.recorded',
            "Recorded {$payment->amount} against {$model->invoice_number}",
            $payment,
            ['invoice' => $model->invoice_number, 'amount' => $payment->amount]
        );

        $this->notify->paymentReceived($request->user(), $model->fresh(), (float) $payment->amount);

        return (new PaymentResource($payment))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $payment): PaymentResource
    {
        $model = Payment::query()
            ->ownedBy($request->user())
            ->with(['invoice.lease.tenant', 'recorder'])
            ->findOrFail($payment);

        return new PaymentResource($model);
    }

    /** Reverse a payment recorded in error; the invoice balance is restored. */
    public function destroy(Request $request, int $payment): JsonResponse
    {
        $this->must($request, 'payments.reverse');

        $model = Payment::query()->ownedBy($request->user())->findOrFail($payment);

        $summary = "Reversed {$model->amount} on invoice #{$model->invoice_id}";

        $this->payments->delete($model);

        $this->log->record($request->user(), 'payment.reversed', $summary, null, [
            'invoice_id' => $model->invoice_id,
            'amount' => $model->amount,
        ]);

        return response()->json(['message' => 'Payment reversed.']);
    }
}
