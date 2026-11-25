<?php

namespace App\Http\Resources;

use App\Models\Shop;
use Illuminate\Http\Request;

/** @mixin Shop */
class ShopDetailResource extends ShopCardResource
{
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'phone' => $this->phone,
            'area' => $this->area,
            'landmark' => $this->landmark,
            'opening_hours' => $this->opening_hours,
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => ['id' => $photo->id, 'url' => $photo->url()])),
            'on_your_list' => (bool) $this->resource->getAttribute('on_your_list'),
        ];
    }
}
