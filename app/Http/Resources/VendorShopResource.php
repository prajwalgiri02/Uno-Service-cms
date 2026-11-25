<?php

namespace App\Http\Resources;

use App\Models\Offer;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Shop */
class VendorShopResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $offer = $this->currentOffer;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'initials' => $this->initials(),
            'category' => new CategoryResource($this->category),
            'phone' => $this->phone,
            'area' => $this->area,
            'landmark' => $this->landmark,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'opening_hours' => $this->opening_hours,
            'blurb' => $this->blurb,
            'vibes' => $this->vibes ?? [],
            'women_run' => $this->women_run,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_note' => $this->status_note,
            'is_live' => $this->isLive(),
            'check_in_code' => $this->check_in_code,
            'open_today' => $this->isOpenToday(),
            'open_confirmed_at' => $this->isOpenToday() ? $this->open_confirmed_at?->toIso8601String() : null,
            'no_wait' => $this->noWaitActive(),
            'no_wait_until' => $this->noWaitActive() ? $this->no_wait_until->toIso8601String() : null,
            'current_offer' => self::offer($offer),
            'services' => ServiceResource::collection($this->whenLoaded('services')),
            'photos' => $this->whenLoaded('photos', fn () => $this->photos->map(fn ($photo) => ['id' => $photo->id, 'url' => $photo->url()])),
            'submitted_at' => $this->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function offer(?Offer $offer): ?array
    {
        return $offer ? [
            'id' => $offer->id,
            'percent' => $offer->percent,
            'weekdays' => $offer->weekdays,
            'from' => substr($offer->from_time, 0, 5),
            'to' => substr($offer->to_time, 0, 5),
            'label' => $offer->label(),
            'running_now' => $offer->isRunningAt(),
            'ends_on' => $offer->ends_on->toDateString(),
        ] : null;
    }
}
