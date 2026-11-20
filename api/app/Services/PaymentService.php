<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Record a payment against an invoice and keep the invoice totals in sync.
     * Runs in a transaction with a row lock so two concurrent payments can't
     * both read a stale balance and overpay the invoice.
     */
    public function record(Invoice $invoice, array $data, ?User $recordedBy = null): Payment
    {
        return DB::transaction(function () use ($invoice, $data, $recordedBy) {
            /** @var Invoice $locked */
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status === InvoiceStatus::Cancelled) {
                throw ValidationException::withMessages([
                    'invoice' => 'This invoice has been cancelled and cannot receive payments.',
                ]);
            }

            $amount = round((float) $data['amount'], 2);

            if ($amount > $locked->balance + 0.001) {
                throw ValidationException::withMessages([
                    'amount' => sprintf(
                        'Amount exceeds the outstanding balance of %s on this invoice.',
                        number_format($locked->balance, 2)
                    ),
                ]);
            }

            $payment = $locked->payments()->create([
                'recorded_by' => $recordedBy?->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->refreshInvoice($locked);

            return $payment->load('invoice');
        });
    }

    /** Remove a payment (correction of a mistake) and restore the invoice totals. */
    public function delete(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($payment->invoice_id);

            $payment->delete();

            $this->refreshInvoice($invoice);
        });
    }

    /** Recompute amount_paid from the payment rows, then re-derive the status. */
    private function refreshInvoice(Invoice $invoice): void
    {
        $invoice->amount_paid = round((float) $invoice->payments()->sum('amount'), 2);
        $invoice->syncStatus();
        $invoice->save();
    }
}
