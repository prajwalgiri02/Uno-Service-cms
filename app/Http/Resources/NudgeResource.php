<?php

namespace App\Http\Resources;

use App\Models\Nudge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Nudge */
class NudgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'combined' => $this->isCombined(),
            'distance_m' => $this->distance_m,
            'result' => $this->result->value,
            'errand_ids' => $this->errand_ids,
            'shops' => ShopCardResource::collection($this->whenLoaded('shops')),
            'opened_at' => $this->opened_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
