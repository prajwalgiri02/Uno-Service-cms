<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationRequest;
use App\Http\Resources\ErrandResource;
use App\Http\Resources\ShopCardResource;
use App\Models\CheckIn;
use App\Models\Errand;
use App\Models\Shop;
use App\Models\User;
use App\Services\CustomerStatsService;
use App\Services\ProfileService;
use App\Services\ShopFinder;
use App\Support\Geo;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    use ApiResponse;

    private const TRIP_RADIUS_M = 500;

    private const NEAR_YOU_RADIUS_M = 1000;

    private const NEIGHBOURHOOD_M = 2000;

    public function __construct(
        private readonly ShopFinder $finder,
        private readonly CustomerStatsService $stats,
        private readonly ProfileService $profile,
    ) {}

    public function __invoke(LocationRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');
        $lat = $request->lat();
        $lng = $request->lng();

        $errands = $user->errands()->watching()->with('category.children', 'category.parent')->get();
        $categoryIds = $errands->flatMap(fn (Errand $e) => $e->category->matchingIds())->unique()->values()->all();
        $nearby = $categoryIds === [] ? collect() : $this->finder->near($lat, $lng, self::NEAR_YOU_RADIUS_M, $categoryIds);

        $trip = $errands
            ->map(function (Errand $errand) use ($nearby) {
                $ids = $errand->category->matchingIds();
                $shop = $nearby->first(fn (Shop $s) => in_array($s->category_id, $ids, true) && $s->distance_m <= self::TRIP_RADIUS_M && $s->isOpenToday());

                return $shop ? ['errand' => $errand, 'shop' => $shop] : null;
            })
            ->filter()
            ->unique(fn (array $m) => $m['errand']->category_id)
            ->values();

        $stats = $this->stats->stats($user);

        return $this->successResponse([
            'name' => $user->name,
            'nudges' => [
                'enabled' => $user->nudges_enabled,
                'paused_until' => $user->isPaused() ? $user->paused_until->toIso8601String() : null,
                'watching_windows' => $this->profile->freeWindows($user)->where('enabled', true)->pluck('title')->values(),
            ],
            'trip' => [
                'errand_count' => $trip->count(),
                'within_m' => $trip->max(fn (array $m) => $m['shop']->distance_m),
                'errands' => $trip->map(fn (array $m) => ['errand_id' => $m['errand']->id, 'title' => $m['errand']->title, 'shop_id' => $m['shop']->id, 'distance_m' => $m['shop']->distance_m]),
            ],
            'errands_done_this_month' => $stats['errands_done_this_month'],
            'streak_weeks' => $stats['streak_weeks'],
            'people_nearby_today' => $this->peopleNearbyToday($lat, $lng),
            'near_you' => ShopCardResource::collection($nearby->take(3)),
            'stale_errand' => ($stale = $errands->first(fn (Errand $e) => $e->isStale())) ? new ErrandResource($stale) : null,
        ]);
    }

    private function peopleNearbyToday(float $lat, float $lng): int
    {
        $shopIds = Geo::within(Shop::query()->live(), $lat, $lng, self::NEIGHBOURHOOD_M)->pluck('id');

        return CheckIn::query()
            ->whereIn('shop_id', $shopIds)
            ->where('created_at', '>=', today())
            ->distinct()
            ->count('user_id');
    }
}
