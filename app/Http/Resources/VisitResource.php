<?php

namespace App\Http\Resources;

use App\Models\CheckIn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CheckIn */
class VisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shop' => [
                'id' => $this->shop->id,
                'name' => $this->shop->name,
                'emoji' => $this->shop->category?->emoji,
                'category' => $this->shop->category?->name,
            ],
            'method' => $this->method->value,
            'points' => $this->points,
            'saved_rs' => $this->saved_rs,
            'minutes_saved' => $this->minutes_saved,
            'first_visit' => $this->first_visit,
            'review' => $this->whenLoaded('review', fn () => $this->review ? ['stars' => $this->review->stars, 'text' => $this->review->text] : null),
            'can_review' => $this->relationLoaded('review') && $this->review === null && $this->created_at->gt(now()->subDays(7)),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
