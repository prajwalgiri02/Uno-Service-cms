<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationRequest;
use App\Http\Requests\Api\OptionalLocationRequest;
use App\Http\Requests\Api\SaveErrandRequest;
use App\Http\Resources\ErrandResource;
use App\Http\Resources\ShopCardResource;
use App\Models\Category;
use App\Models\Errand;
use App\Services\ErrandService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ErrandController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ErrandService $errands,
    ) {}

    public function index(OptionalLocationRequest $request): JsonResponse
    {
        $errands = $request->user('api')->errands()->open()
            ->with('category.parent', 'category.children')
            ->orderByDesc('active')
            ->latest()
            ->get();

        if ($request->hasLocation()) {
            $errands->each(fn (Errand $errand) => $errand->setAttribute(
                'open_nearby',
                $errand->active ? $this->errands->matches($errand, $request->lat(), $request->lng())->filter->isOpenToday()->count() : 0,
            ));
        }

        return $this->successResponse([
            'errands' => ErrandResource::collection($errands),
            'stale' => ($stale = $errands->first(fn (Errand $e) => $e->isStale())) ? new ErrandResource($stale) : null,
        ]);
    }

    public function store(SaveErrandRequest $request): JsonResponse
    {
        $errand = $this->errands->create($request->user('api'), $request->validated());

        return $this->resourceResponse(new ErrandResource($errand->load('category.parent')), "{$errand->title} is on your list ✦", 201);
    }

    public function update(SaveErrandRequest $request, Errand $errand): JsonResponse
    {
        $this->authorizeOwner($request, $errand);

        return $this->resourceResponse(new ErrandResource($this->errands->update($errand, $request->validated())->load('category.parent')));
    }

    public function keep(Request $request, Errand $errand): JsonResponse
    {
        $this->authorizeOwner($request, $errand);

        return $this->resourceResponse(new ErrandResource($this->errands->keep($errand)->load('category.parent')), 'Kept on your list.');
    }

    public function done(Request $request, Errand $errand): JsonResponse
    {
        $this->authorizeOwner($request, $errand);

        return $this->resourceResponse(new ErrandResource($this->errands->markDone($errand)->load('category.parent')), "{$errand->title} is off your list ✓");
    }

    public function destroy(Request $request, Errand $errand): JsonResponse
    {
        $this->authorizeOwner($request, $errand);
        $errand->delete();

        return $this->successResponse(null, 'Removed from your list.');
    }

    public function pauseCategory(Request $request, Category $category): JsonResponse
    {
        $count = $this->errands->pauseCategory($request->user('api'), $category);

        return $this->successResponse(['paused' => $count], "{$category->name} paused on your list");
    }

    public function matches(LocationRequest $request, Errand $errand): JsonResponse
    {
        $this->authorizeOwner($request, $errand);
        $errand->load('category.children');

        return $this->successResponse([
            'errand' => new ErrandResource($errand->load('category.parent')),
            'shops' => ShopCardResource::collection($this->errands->matches($errand, $request->lat(), $request->lng())),
            'also_on_the_way' => ShopCardResource::collection($this->errands->alsoOnTheWay($request->user('api'), $errand, $request->lat(), $request->lng())),
        ]);
    }

    private function authorizeOwner(Request $request, Errand $errand): void
    {
        abort_unless($errand->user_id === $request->user('api')->id, 404);
    }
}
