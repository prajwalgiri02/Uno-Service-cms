<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CheckInRequest;
use App\Http\Requests\Api\ReviewRequest;
use App\Http\Resources\VisitResource;
use App\Models\CheckIn;
use App\Services\CheckInService;
use App\Services\CustomerStatsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly CheckInService $checkIns,
    ) {}

    public function store(CheckInRequest $request, CustomerStatsService $stats): JsonResponse
    {
        $user = $request->user('api');
        $checkIn = $this->checkIns->checkInWithShopCode(
            $user,
            $request->string('code')->toString(),
            $request->lat(),
            $request->lng(),
            $request->filled('shop_id') ? $request->integer('shop_id') : null,
        );

        $user->refresh();

        return $this->successResponse([
            'visit' => new VisitResource($checkIn->load('review')),
            'points_earned' => $checkIn->points,
            'points' => $user->points_balance,
            'streak_weeks' => $stats->streakWeeks($user),
        ], "You're checked in. +{$checkIn->points} points", 201);
    }

    /**
     * The code a shop types when the customer cannot scan.
     */
    public function visitCode(Request $request): JsonResponse
    {
        return $this->successResponse([
            'code' => $this->checkIns->visitCode($request->user('api')),
            'expires_in' => CheckInService::VISIT_CODE_MINUTES * 60,
        ]);
    }

    public function review(ReviewRequest $request, CheckIn $checkIn): JsonResponse
    {
        $review = $this->checkIns->review(
            $request->user('api'),
            $checkIn,
            $request->integer('stars'),
            $request->input('text'),
        );

        return $this->successResponse(['stars' => $review->stars, 'text' => $review->text], 'Thanks for rating your visit.', 201);
    }
}
