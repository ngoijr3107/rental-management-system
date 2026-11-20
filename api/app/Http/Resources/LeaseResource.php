<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'tenant_id' => $this->tenant_id,
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'terminated_at' => $this->terminated_at?->toDateString(),
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'billing_day' => $this->billing_day,
            'billing_period_months' => $this->periodMonths(),
            'period_amount' => number_format($this->periodAmount(), 2, '.', ''),
            'status' => $this->status,
            'notes' => $this->notes,
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'invoices' => InvoiceResource::collection($this->whenLoaded('invoices')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
