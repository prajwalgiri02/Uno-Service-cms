<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Review;
use App\Models\Shop;
use App\Services\VendorInsightsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    use ApiResponse;

    public function index(Request $request, VendorInsightsService $insights): JsonResponse
    {
        $data = $request->validate(['filter' => ['sometimes', Rule::in(['all', 'low', 'needs_reply'])]]);
        $shop = $this->shop($request);

        $reviews = $shop->reviews()
            ->with('user')
            ->when(($data['filter'] ?? 'all') === 'low', fn ($q) => $q->where('stars', '<=', Review::LOW_STARS))
            ->when(($data['filter'] ?? 'all') === 'needs_reply', fn ($q) => $q->where('stars', '<=', Review::LOW_STARS)->whereNull('reply'))
            ->latest()
            ->paginate(20);

        return $this->successResponse([
            'summary' => $insights->reviewSummary($shop),
            'reviews' => ReviewResource::collection($reviews),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    public function reply(Request $request, Review $review): JsonResponse
    {
        abort_unless($review->shop_id === $this->shop($request)->id, 404);

        $data = $request->validate(['reply' => ['required', 'string', 'max:1000']]);
        $review->forceFill(['reply' => $data['reply'], 'replied_at' => now()])->save();

        return $this->resourceResponse(new ReviewResource($review->load('user')), 'Reply posted.');
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
