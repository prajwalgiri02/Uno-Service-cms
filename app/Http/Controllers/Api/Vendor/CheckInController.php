<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Services\CheckInService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    use ApiResponse;

    /**
     * The shop types the 4-digit code shown in the customer's app.
     */
    public function store(Request $request, CheckInService $checkIns): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:4']]);
        $shop = $request->attributes->get('shop');

        $checkIn = $checkIns->confirmWithCustomerCode($shop, $data['code']);

        return $this->successResponse([
            'check_in_id' => $checkIn->id,
            'customer' => $checkIn->user->name ?? 'Najik customer',
            'first_visit' => $checkIn->first_visit,
            'visits_today' => $shop->checkIns()->where('created_at', '>=', today())->count(),
        ], 'Visit confirmed ✓', 201);
    }
}
