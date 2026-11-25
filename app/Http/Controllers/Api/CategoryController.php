<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\CategoryRequest;
use App\Services\ShopFinder;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    private const PRICE_HINT_RADIUS_M = 2000;

    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->active()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->where('status', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return $this->successResponse(CategoryResource::collection($categories));
    }

    /**
     * "most shops nearby charge Rs a–b"
     */
    public function priceHint(LocationRequest $request, Category $category, ShopFinder $finder): JsonResponse
    {
        $shops = $finder->near($request->lat(), $request->lng(), self::PRICE_HINT_RADIUS_M, $category->matchingIds());
        $min = $shops->map->priceMin()->filter()->min();
        $max = $shops->map->priceMax()->filter()->max();

        return $this->successResponse([
            'shops' => $shops->count(),
            'price_min' => $min ?? $category->typical_price_min,
            'price_max' => $max ?? $category->typical_price_max,
            'source' => $shops->isEmpty() ? 'typical' : 'nearby',
        ]);
    }

    /**
     * "we don't have that yet — we've noted it"
     */
    public function requestNew(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'max:80']]);

        CategoryRequest::query()->create(['user_id' => $request->user('api')->id, 'query' => trim($data['query'])]);

        return $this->successResponse(null, "We don't have that yet. We've noted it.", 201);
    }
}
