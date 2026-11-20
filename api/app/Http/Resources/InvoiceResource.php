<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lease_id' => $this->lease_id,
            'invoice_number' => $this->invoice_number,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'amount' => $this->amount,
            'amount_paid' => $this->amount_paid,
            'balance' => $this->balance,
            'status' => $this->status,
            'is_overdue' => $this->isOverdue(),
            'notes' => $this->notes,
            'lease' => new LeaseResource($this->whenLoaded('lease')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
