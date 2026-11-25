<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LocationRequest;
use App\Http\Resources\NudgeResource;
use App\Models\Nudge;
use App\Services\NudgeEngine;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NudgeController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly NudgeEngine $engine,
    ) {}

    /**
     * Called by the app's background location watcher.
     */
    public function check(LocationRequest $request): JsonResponse
    {
        ['nudge' => $nudge, 'reason' => $reason] = $this->engine->check($request->user('api'), $request->lat(), $request->lng());

        return $this->successResponse([
            'sent' => $nudge !== null,
            'reason' => $reason,
            'nudge' => $nudge ? new NudgeResource($nudge->load('shops.category.parent', 'shops.services', 'shops.currentOffer')) : null,
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user('api');
        $nudges = $user->nudges()
            ->with('shops.category.parent', 'shops.services', 'shops.currentOffer')
            ->latest()
            ->paginate(20);

        return $this->successResponse(NudgeResource::collection($nudges));
    }

    public function show(Request $request, Nudge $nudge): JsonResponse
    {
        abort_unless($nudge->user_id === $request->user('api')->id, 404);

        return $this->resourceResponse(new NudgeResource($nudge->load('shops.category.parent', 'shops.services', 'shops.currentOffer')));
    }

    public function opened(Request $request, Nudge $nudge): JsonResponse
    {
        abort_unless($nudge->user_id === $request->user('api')->id, 404);

        return $this->resourceResponse(new NudgeResource($this->engine->markOpened($nudge)));
    }

    /**
     * How many more nudges can arrive today, for the "max 2–3 pings a day" preview.
     */
    public function today(Request $request): JsonResponse
    {
        $user = $request->user('api');

        return $this->successResponse([
            'cap' => $this->engine->dailyCap($user),
            'sent' => $this->engine->sentToday($user),
        ]);
    }
}
