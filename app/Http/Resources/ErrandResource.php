<?php

namespace App\Http\Resources;

use App\Models\Errand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Errand */
class ErrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'name_ne' => $this->category->name_ne,
                'emoji' => $this->category->emoji ?? $this->category->parent?->emoji,
            ],
            'title' => $this->title,
            'variant' => $this->variant,
            'max_price' => $this->max_price,
            'radius_m' => $this->radius_m,
            'only_no_wait' => $this->only_no_wait,
            'offers_only' => $this->offers_only,
            'women_run' => $this->women_run,
            'active' => $this->active,
            'paused_note' => $this->paused_note,
            'is_stale' => $this->done_at === null && $this->isStale(),
            'open_nearby' => $this->resource->getAttribute('open_nearby'),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'done_at' => $this->done_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
