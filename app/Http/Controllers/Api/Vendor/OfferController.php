<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\StartOfferRequest;
use App\Http\Resources\VendorShopResource;
use App\Models\Shop;
use App\Services\OfferService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfferController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly OfferService $offers,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $shop = $this->shop($request)->load('currentOffer');

        return $this->successResponse([
            'busy' => $this->offers->busyMap($shop),
            'current_offer' => VendorShopResource::offer($shop->currentOffer),
        ]);
    }

    public function store(StartOfferRequest $request): JsonResponse
    {
        $shop = $this->shop($request);
        $offer = $this->offers->start(
            $shop,
            $request->integer('percent'),
            $request->input('weekdays'),
            $request->string('from')->toString(),
            $request->string('to')->toString(),
        );

        return $this->successResponse(VendorShopResource::offer($offer), "{$offer->label()} is live.", 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $this->offers->stop($this->shop($request));

        return $this->successResponse(null, 'Offer stopped.');
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }
}
