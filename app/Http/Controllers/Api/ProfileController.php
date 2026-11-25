<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterDeviceRequest;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Http\Resources\VisitResource;
use App\Models\User;
use App\Services\CustomerStatsService;
use App\Services\ProfileService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ProfileController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ProfileService $profile,
        private readonly CustomerStatsService $stats,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return $this->resourceResponse(new UserResource($this->user($request)->load(['roles', 'shop'])));
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->user($request);
        $user->fill($request->validated())->save();

        return $this->resourceResponse(new UserResource($user->load(['roles', 'shop'])), 'Profile updated.');
    }

    public function pauseToday(Request $request): JsonResponse
    {
        return $this->resourceResponse(new UserResource($this->profile->pauseToday($this->user($request))), 'Paused for today.');
    }

    public function resume(Request $request): JsonResponse
    {
        return $this->resourceResponse(new UserResource($this->profile->resume($this->user($request))), 'Nudges are back on.');
    }

    public function alertHealth(Request $request): JsonResponse
    {
        return $this->successResponse($this->profile->alertHealth($this->user($request)));
    }

    public function registerDevice(RegisterDeviceRequest $request): JsonResponse
    {
        $this->profile->registerDevice($this->user($request), $request->validated());

        return $this->successResponse(null, 'Device registered.');
    }

    public function removeDevice(Request $request, string $deviceId): JsonResponse
    {
        $this->user($request)->deviceTokens()->where('device_id', $deviceId)->delete();

        return $this->successResponse(null, 'Device removed.');
    }

    public function stats(Request $request): JsonResponse
    {
        return $this->successResponse($this->stats->stats($this->user($request)));
    }

    public function visits(Request $request): JsonResponse
    {
        $request->validate(['month' => ['sometimes', 'date_format:Y-m']]);

        $month = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->string('month')->toString())->startOfMonth() : now()->startOfMonth();
        $visits = $this->stats->visitsInMonth($this->user($request), $month);

        return $this->successResponse([
            'month' => $month->format('Y-m'),
            'points' => (int) $visits->sum('points'),
            'saved_rs' => (int) $visits->sum('saved_rs'),
            'minutes_saved' => (int) $visits->sum('minutes_saved'),
            'visits' => VisitResource::collection($visits),
        ]);
    }

    private function user(Request $request): User
    {
        return $request->user('api');
    }
}
