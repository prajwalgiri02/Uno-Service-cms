<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveFreeWindowRequest;
use App\Http\Resources\FreeWindowResource;
use App\Models\FreeWindow;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FreeWindowController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProfileService $profile,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(FreeWindowResource::collection($this->profile->freeWindows($request->user('api'))));
    }

    public function store(SaveFreeWindowRequest $request): JsonResponse
    {
        $window = $this->profile->saveWindow($request->user('api'), $request->validated());

        return $this->resourceResponse(new FreeWindowResource($window), 'Window added.', 201);
    }

    public function update(SaveFreeWindowRequest $request, FreeWindow $freeWindow): JsonResponse
    {
        $this->authorizeOwner($request, $freeWindow);

        return $this->resourceResponse(new FreeWindowResource($this->profile->saveWindow($request->user('api'), $request->validated(), $freeWindow)));
    }

    public function destroy(Request $request, FreeWindow $freeWindow): JsonResponse
    {
        $this->authorizeOwner($request, $freeWindow);

        if ($freeWindow->preset !== null) {
            return $this->errorResponse('Preset windows can be switched off but not deleted.', 422);
        }

        $freeWindow->delete();

        return $this->successResponse(null, 'Window removed.');
    }

    private function authorizeOwner(Request $request, FreeWindow $window): void
    {
        abort_unless($window->user_id === $request->user('api')->id, 404);
    }
}
