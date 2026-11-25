<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Shop;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Finds live shops around a point. Distances are exact (haversine) after a
 * bounding-box prefilter, so this works on any SQL database.
 */
class ShopFinder
{
    /**
     * @param  list<int>|null  $categoryIds
     * @param  array{no_wait?: bool, offers?: bool, women_run?: bool, open_now?: bool, search?: string|null}  $filters
     * @return Collection<int, Shop> sorted by distance, each with a `distance_m` attribute
     */
    public function near(float $lat, float $lng, int $radiusM, ?array $categoryIds = null, array $filters = [], ?int $limit = null): Collection
    {
        $query = Shop::query()
            ->live()
            ->with(['category.parent', 'services', 'currentOffer'])
            ->withAvg('reviews', 'stars')
            ->withCount('reviews')
            ->when($categoryIds !== null, fn (Builder $q) => $q->whereIn('category_id', $categoryIds))
            ->when($filters['women_run'] ?? false, fn (Builder $q) => $q->where('women_run', true))
            ->when($filters['no_wait'] ?? false, fn (Builder $q) => $q->where('no_wait_until', '>', now()))
            ->when(filled($filters['search'] ?? null), function (Builder $q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('name', 'like', $term)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $term)->orWhere('name_ne', 'like', $term))
                    ->orWhereHas('services', fn (Builder $s) => $s->where('name', 'like', $term)));
            });

        $shops = Geo::within($query, $lat, $lng, $radiusM)->get()
            ->each(fn (Shop $shop) => $shop->setAttribute('distance_m', Geo::distanceM($lat, $lng, $shop->latitude, $shop->longitude)))
            ->filter(fn (Shop $shop) => $shop->distance_m <= $radiusM)
            ->when($filters['offers'] ?? false, fn ($c) => $c->filter(fn (Shop $shop) => $shop->currentOffer !== null))
            ->when($filters['open_now'] ?? false, fn ($c) => $c->filter(fn (Shop $shop) => $shop->isOpenToday() && $shop->isWithinHours()))
            ->sortBy('distance_m')
            ->values();

        return $limit ? $shops->take($limit)->values() : $shops;
    }
}
