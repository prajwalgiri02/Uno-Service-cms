<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Services\VendorInsightsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsightController extends Controller
{
    use ApiResponse;

    /**
     * "How Najik helped": reminder → page view → visit.
     */
    public function __invoke(Request $request, VendorInsightsService $insights): JsonResponse
    {
        $data = $request->validate(['period' => ['sometimes', Rule::in(['today', 'week', 'month'])]]);

        return $this->successResponse($insights->funnel($request->attributes->get('shop'), $data['period'] ?? 'week'));
    }
}
