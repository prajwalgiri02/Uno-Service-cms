<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class Geo
{
    private const EARTH_RADIUS_M = 6371000;

    public static function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (int) round(self::EARTH_RADIUS_M * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Cheap index-friendly prefilter; callers still check the exact distance.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public static function within(Builder $query, float $lat, float $lng, int $meters, string $latColumn = 'latitude', string $lngColumn = 'longitude'): Builder
    {
        $dLat = $meters / 111320;
        $dLng = $meters / (111320 * max(cos(deg2rad($lat)), 0.01));

        return $query
            ->whereBetween($latColumn, [$lat - $dLat, $lat + $dLat])
            ->whereBetween($lngColumn, [$lng - $dLng, $lng + $dLng]);
    }

    public static function walkMinutes(int $meters): int
    {
        return max(1, (int) ceil($meters / 75));
    }
}
