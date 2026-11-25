<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OptionalLocationRequest;
use App\Http\Requests\Api\ReportShopRequest;
use App\Http\Requests\Api\ShopSearchRequest;
use App\Http\Resources\ShopCardResource;
use App\Http\Resources\ShopDetailResource;
use App\Models\Category;
use App\Models\Errand;
use App\Models\Shop;
use App\Models\ShopView;
use App\Models\User;
use App\Services\ReportService;
use App\Services\ShopFinder;
use App\Support\Geo;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    use ApiResponse;

    private const DEFAULT_RADIUS_M = 2000;

    public function __construct(
        private readonly ShopFinder $finder,
    ) {}

    public function index(ShopSearchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');
        $categoryIds = null;

        if ($request->filled('category_id')) {
            $categoryIds = Category::query()->findOrFail($request->integer('category_id'))->matchingIds();
        } elseif ($request->boolean('on_list')) {
            $categoryIds = $this->listCategoryIds($user);
        }

        $shops = $this->finder->near(
            $request->lat(),
            $request->lng(),
            $request->integer('radius_m', self::DEFAULT_RADIUS_M),
            $categoryIds,
            $request->filters(),
        );

        $this->markFavorites($user, $shops);

        return $this->successResponse([
            'list_count' => $user->errands()->watching()->count(),
            'shops' => ShopCardResource::collection($shops),
        ]);
    }

    public function show(OptionalLocationRequest $request, Shop $shop): JsonResponse
    {
        abort_unless($shop->isLive(), 404);

        /** @var User $user */
        $user = $request->user('api');

        $shop->load(['category.parent', 'services', 'photos', 'currentOffer'])->loadAvg('reviews', 'stars')->loadCount('reviews');

        if ($request->hasLocation()) {
            $shop->setAttribute('distance_m', Geo::distanceM($request->lat(), $request->lng(), $shop->latitude, $shop->longitude));
        }

        $shop->setAttribute('is_favorite', $user->favoriteShops()->whereKey($shop->id)->exists());
        $shop->setAttribute('on_your_list', in_array($shop->category_id, $this->listCategoryIds($user), true));

        ShopView::query()->create(['shop_id' => $shop->id, 'user_id' => $user->id]);

        return $this->resourceResponse(new ShopDetailResource($shop));
    }

    public function favorites(Request $request): JsonResponse
    {
        $shops = $request->user('api')->favoriteShops()->live()
            ->with(['category.parent', 'services', 'currentOffer'])
            ->withAvg('reviews', 'stars')
            ->withCount('reviews')
            ->get()
            ->each(fn (Shop $shop) => $shop->setAttribute('is_favorite', true));

        return $this->successResponse(ShopCardResource::collection($shops));
    }

    public function favorite(Request $request, Shop $shop): JsonResponse
    {
        abort_unless($shop->isLive(), 404);
        $request->user('api')->favoriteShops()->syncWithoutDetaching([$shop->id]);

        return $this->successResponse(['favorite' => true], 'Saved.');
    }

    public function unfavorite(Request $request, Shop $shop): JsonResponse
    {
        $request->user('api')->favoriteShops()->detach($shop->id);

        return $this->successResponse(['favorite' => false], 'Removed from saved.');
    }

    public function report(ReportShopRequest $request, Shop $shop, ReportService $reports): JsonResponse
    {
        $reports->create($request->user('api'), $shop, ReportReason::from($request->string('reason')->toString()), $request->input('note'));

        return $this->successResponse(null, "Thanks. We'll check {$shop->name}.", 201);
    }

    /**
     * @return list<int>
     */
    private function listCategoryIds(User $user): array
    {
        return $user->errands()->watching()->with('category.children')->get()
            ->flatMap(fn (Errand $e) => $e->category->matchingIds())
            ->unique()->values()->all();
    }

    /**
     * @param  iterable<Shop>  $shops
     */
    private function markFavorites(User $user, iterable $shops): void
    {
        $favorites = $user->favoriteShops()->pluck('shops.id')->all();

        foreach ($shops as $shop) {
            $shop->setAttribute('is_favorite', in_array($shop->id, $favorites, true));
        }
    }
}
