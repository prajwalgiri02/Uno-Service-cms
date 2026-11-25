<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vendor\SaveShopRequest;
use App\Http\Resources\VendorShopResource;
use App\Models\Shop;
use App\Models\ShopPhoto;
use App\Services\VendorInsightsService;
use App\Services\VendorShopService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ShopController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VendorShopService $shops,
    ) {}

    public function store(SaveShopRequest $request): JsonResponse
    {
        $shop = $this->shops->create($request->user('api'), $request->validated());

        return $this->resourceResponse(new VendorShopResource($this->fresh($shop)), 'Shop submitted. Najik reviews new shops within a day.', 201);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->resourceResponse(new VendorShopResource($this->fresh($this->shop($request))));
    }

    public function update(SaveShopRequest $request): JsonResponse
    {
        $shop = $this->shops->update($this->shop($request), $request->validated());

        return $this->resourceResponse(new VendorShopResource($this->fresh($shop)), 'Shop updated.');
    }

    /**
     * The "Today" tab: live status plus today's numbers.
     */
    public function today(Request $request, VendorInsightsService $insights): JsonResponse
    {
        $shop = $this->shop($request);

        return $this->successResponse([
            'shop' => new VendorShopResource($this->fresh($shop)),
            ...$insights->today($shop),
        ]);
    }

    public function openToday(Request $request): JsonResponse
    {
        $data = $request->validate(['open' => ['required', 'boolean']]);
        $shop = $this->shops->setOpenToday($this->shop($request), $data['open']);

        return $this->resourceResponse(
            new VendorShopResource($this->fresh($shop)),
            $data['open'] ? 'Confirmed. Nearby people can be nudged.' : 'No one will be nudged about you today.',
        );
    }

    public function noWait(Request $request): JsonResponse
    {
        $data = $request->validate(['on' => ['required', 'boolean']]);
        $shop = $this->shops->setNoWait($this->shop($request), $data['on']);

        return $this->resourceResponse(new VendorShopResource($this->fresh($shop)), $data['on'] ? '"No wait now" is on.' : '"No wait now" is off.');
    }

    public function addPhoto(Request $request): JsonResponse
    {
        $request->validate(['photo' => ['required', 'image', 'max:5120']]);
        $photo = $this->shops->addPhoto($this->shop($request), $request->file('photo'));

        return $this->successResponse(['id' => $photo->id, 'url' => $photo->url()], 'Photo added.', 201);
    }

    public function deletePhoto(Request $request, ShopPhoto $photo): JsonResponse
    {
        abort_unless($photo->shop_id === $this->shop($request)->id, 404);
        $this->shops->deletePhoto($photo);

        return $this->successResponse(null, 'Photo removed.');
    }

    public function checkInCode(Request $request): JsonResponse
    {
        $shop = $this->shop($request);

        return $this->successResponse($this->codePayload($shop));
    }

    public function rotateCheckInCode(Request $request): JsonResponse
    {
        $shop = $this->shops->rotateCheckInCode($this->shop($request));

        return $this->successResponse($this->codePayload($shop), 'New code is live. The old code stops working now.');
    }

    /**
     * @return array<string, mixed>
     */
    private function codePayload(Shop $shop): array
    {
        return [
            'code' => $shop->check_in_code,
            'qr_payload' => 'najik://check-in/'.$shop->check_in_code,
            'check_ins_today' => $shop->checkIns()->where('created_at', '>=', today())->count(),
            'check_ins_this_week' => $shop->checkIns()->where('created_at', '>=', now()->startOfWeek(Carbon::SUNDAY))->count(),
        ];
    }

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function fresh(Shop $shop): Shop
    {
        return $shop->refresh()->load(['category.children', 'services', 'photos', 'currentOffer']);
    }
}
