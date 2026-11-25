<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\SaveServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Shop;
use App\Models\ShopService;
use App\Services\VendorShopService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VendorShopService $shops,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->successResponse(ServiceResource::collection($this->shop($request)->services));
    }

    public function store(SaveServiceRequest $request): JsonResponse
    {
        $service = $this->shops->saveService($this->shop($request), $request->attributesToSave(), photo: $request->file('photo'));

        return $this->resourceResponse(new ServiceResource($service), 'Service saved.', 201);
    }

    public function update(SaveServiceRequest $request, ShopService $service): JsonResponse
    {
        $this->authorizeOwner($request, $service);
        $service = $this->shops->saveService($this->shop($request), $request->attributesToSave(), $service, $request->file('photo'));

        return $this->resourceResponse(new ServiceResource($service), 'Service updated.');
    }

    public function destroy(Request $request, ShopService $service): JsonResponse
    {
        $this->authorizeOwner($request, $service);
        $this->shops->deleteService($service);

        return $this->successResponse(null, 'Service removed.');
    }

    private function authorizeOwner(Request $request, ShopService $service): void
    {
        abort_unless($service->shop_id === $this->shop($request)->id, 404);
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
