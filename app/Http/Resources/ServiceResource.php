<?php

namespace App\Http\Resources;

use App\Models\ShopService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin ShopService */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'for_who' => $this->for_who,
            'minutes' => $this->minutes,
            'price_min' => $this->price_min,
            'price_max' => $this->price_max,
            'walk_ins' => $this->walk_ins,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
        ];
    }
}
