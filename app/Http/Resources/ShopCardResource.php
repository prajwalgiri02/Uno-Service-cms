<?php

namespace App\Http\Resources;

use App\Models\Shop;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shop */
class ShopCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $distance = $this->resource->getAttribute('distance_m');
        $offer = $this->relationLoaded('currentOffer') ? $this->currentOffer : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials(),
            'emoji' => $this->category?->emoji ?? $this->category?->parent?->emoji,
            'category' => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
                'name_ne' => $this->category?->name_ne,
                'parent' => $this->category?->parent?->name,
            ],
            'headline' => $this->relationLoaded('services') ? ($this->services->first()?->name ?? $this->category?->name) : $this->category?->name,
            'blurb' => $this->blurb,
            'vibes' => $this->vibes ?? [],
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'distance_m' => $distance,
            'walk_min' => $distance === null ? null : Geo::walkMinutes($distance),
            'open_today' => $this->isOpenToday(),
            'open_now' => $this->isOpenToday() && $this->isWithinHours(),
            'open_till' => $this->openTill(),
            'price_min' => $this->priceMin(),
            'price_max' => $this->priceMax(),
            'no_wait' => $this->noWaitActive(),
            'no_wait_until' => $this->no_wait_until?->toIso8601String(),
            'offer' => $offer ? [
                'percent' => $offer->percent,
                'label' => $offer->label(),
                'running_now' => $offer->isRunningAt(),
            ] : null,
            'rating' => $this->reviews_avg_stars !== null ? round((float) $this->reviews_avg_stars, 1) : null,
            'rating_count' => $this->reviews_count ?? 0,
            'women_run' => $this->women_run,
            'favorite' => (bool) $this->resource->getAttribute('is_favorite'),
        ];
    }
}
