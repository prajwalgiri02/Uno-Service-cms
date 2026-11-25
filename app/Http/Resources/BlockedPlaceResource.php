<?php

namespace App\Http\Resources;

use App\Models\BlockedPlace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin BlockedPlace */
class BlockedPlaceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'label' => $this->label,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_m' => $this->radius_m,
            'enabled' => $this->enabled,
            'has_location' => $this->latitude !== null && $this->longitude !== null,
        ];
    }
}
