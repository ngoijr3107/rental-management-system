<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'tenant_id' => $this->tenant_id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'status' => $this->status,
            'cost' => $this->cost,
            'reported_at' => $this->reported_at?->toDateString(),
            'resolved_at' => $this->resolved_at?->toDateString(),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'tenant' => new TenantResource($this->whenLoaded('tenant')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
