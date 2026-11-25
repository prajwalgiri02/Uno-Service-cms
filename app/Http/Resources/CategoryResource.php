<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'name_ne' => $this->name_ne,
            'slug' => $this->slug,
            'emoji' => $this->emoji,
            'options' => $this->options ?? [],
            'keywords' => $this->keywords ?? [],
            'typical_price_min' => $this->typical_price_min,
            'typical_price_max' => $this->typical_price_max,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
