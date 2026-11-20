<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'property_id' => $this->property_id,
            'label' => $this->label,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'size_sqm' => $this->size_sqm,
            'rent_amount' => $this->rent_amount,
            'deposit_amount' => $this->deposit_amount,
            'status' => $this->status,
            'description' => $this->description,
            'cover_photo' => $this->whenLoaded('coverPhoto', fn () => $this->coverPhoto?->url()),
            'photos' => UnitPhotoResource::collection($this->whenLoaded('photos')),
            'photos_count' => $this->whenCounted('photos'),
            'property' => new PropertyResource($this->whenLoaded('property')),
            'active_lease' => new LeaseResource($this->whenLoaded('activeLease')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
