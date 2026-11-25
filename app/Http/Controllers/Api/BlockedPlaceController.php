<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveBlockedPlaceRequest;
use App\Http\Resources\BlockedPlaceResource;
use App\Models\BlockedPlace;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlockedPlaceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProfileService $profile,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(BlockedPlaceResource::collection($this->profile->blockedPlaces($request->user('api'))));
    }

    public function store(SaveBlockedPlaceRequest $request): JsonResponse
    {
        $place = $request->user('api')->blockedPlaces()->create([...$request->validated(), 'kind' => 'custom']);

        return $this->resourceResponse(new BlockedPlaceResource($place), 'Place added.', 201);
    }

    public function update(SaveBlockedPlaceRequest $request, BlockedPlace $blockedPlace): JsonResponse
    {
        $this->authorizeOwner($request, $blockedPlace);
        $blockedPlace->fill($request->validated())->save();

        return $this->resourceResponse(new BlockedPlaceResource($blockedPlace));
    }

    public function destroy(Request $request, BlockedPlace $blockedPlace): JsonResponse
    {
        $this->authorizeOwner($request, $blockedPlace);

        if ($blockedPlace->kind !== 'custom') {
            return $this->errorResponse('Home and work can be switched off but not deleted.', 422);
        }

        $blockedPlace->delete();

        return $this->successResponse(null, 'Place removed.');
    }

    private function authorizeOwner(Request $request, BlockedPlace $place): void
    {
        abort_unless($place->user_id === $request->user('api')->id, 404);
    }
}
