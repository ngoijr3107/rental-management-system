<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitPhotoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'url' => $this->url(),
            'caption' => $this->caption,
            'is_cover' => $this->is_cover,
            'position' => $this->position,
        ];
    }
}
