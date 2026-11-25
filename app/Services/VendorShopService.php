<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ShopStatus;
use App\Enums\UserRole;
use App\Models\Shop;
use App\Models\ShopPhoto;
use App\Models\ShopService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class VendorShopService
{
    public const MAX_PHOTOS = 6;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ShopStatusService $shopStatus,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function create(User $owner, array $data): Shop
    {
        if ($owner->shop()->exists()) {
            throw ValidationException::withMessages(['name' => 'You already have a shop on Najik.']);
        }

        return DB::transaction(function () use ($owner, $data) {
            if (! $owner->hasRole(UserRole::VENDOR->value)) {
                $owner->assignRole(UserRole::VENDOR->value);
            }

            $shop = $owner->shop()->create([
                ...$data,
                'phone' => $data['phone'] ?? $owner->phone,
                'status' => ShopStatus::PENDING,
                'submitted_at' => now(),
                'check_in_code' => $this->uniqueCheckInCode(),
            ]);

            $this->shopStatus->change($shop, ShopStatus::PENDING, null, 'Submitted from the app.');

            return $shop;
        });
    }

    /**
     * Editing a shop that was sent back for changes resubmits it for review.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Shop $shop, array $data): Shop
    {
        $shop->fill($data)->save();

        if ($shop->status === ShopStatus::CHANGES_REQUESTED) {
            $shop->forceFill(['submitted_at' => now()])->save();
            $this->shopStatus->change($shop, ShopStatus::PENDING, null, 'Resubmitted after changes.');
        }

        return $shop;
    }

    /**
     * @throws ValidationException
     */
    public function addPhoto(Shop $shop, UploadedFile $file): ShopPhoto
    {
        if ($shop->photos()->count() >= self::MAX_PHOTOS) {
            throw ValidationException::withMessages(['photo' => 'A shop can have up to '.self::MAX_PHOTOS.' photos.']);
        }

        return $shop->photos()->create([
            'path' => $file->store("shops/{$shop->id}", 'public'),
            'sort_order' => (int) $shop->photos()->max('sort_order') + 1,
        ]);
    }

    public function deletePhoto(ShopPhoto $photo): void
    {
        Storage::disk('public')->delete($photo->path);
        $photo->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function saveService(Shop $shop, array $data, ?ShopService $service = null, ?UploadedFile $photo = null): ShopService
    {
        if ($photo) {
            if ($service?->photo_path) {
                Storage::disk('public')->delete($service->photo_path);
            }
            $data['photo_path'] = $photo->store("shops/{$shop->id}/services", 'public');
        }

        if ($service) {
            $service->fill($data)->save();

            return $service;
        }

        return $shop->services()->create([...$data, 'sort_order' => (int) $shop->services()->max('sort_order') + 1]);
    }

    public function deleteService(ShopService $service): void
    {
        if ($service->photo_path) {
            Storage::disk('public')->delete($service->photo_path);
        }

        $service->delete();
    }

    /**
     * @throws ValidationException
     */
    public function setOpenToday(Shop $shop, bool $open): Shop
    {
        if ($open && ! $shop->isLive()) {
            throw ValidationException::withMessages(['open' => 'Your shop goes live after Najik approves it.']);
        }

        $shop->forceFill($open
            ? ['open_today_on' => today(), 'open_confirmed_at' => now()]
            : ['open_today_on' => null, 'no_wait_until' => null])->save();

        return $shop;
    }

    /**
     * "No wait now" switches itself off after the CMS-configured time.
     *
     * @throws ValidationException
     */
    public function setNoWait(Shop $shop, bool $on): Shop
    {
        if ($on && ! $shop->isOpenToday()) {
            throw ValidationException::withMessages(['on' => 'Turn on "open today" first.']);
        }

        $shop->forceFill([
            'no_wait_until' => $on ? now()->addMinutes($this->settings->int('no_wait_minutes')) : null,
        ])->save();

        return $shop;
    }

    public function rotateCheckInCode(Shop $shop): Shop
    {
        $shop->forceFill(['check_in_code' => $this->uniqueCheckInCode()])->save();

        return $shop;
    }

    private function uniqueCheckInCode(): string
    {
        $digits = 4;
        $attempts = 0;

        do {
            if (++$attempts > 20) {
                $digits++;
                $attempts = 0;
            }
            $code = 'NJK-'.random_int(10 ** ($digits - 1), (10 ** $digits) - 1);
        } while (Shop::query()->where('check_in_code', $code)->exists());

        return $code;
    }
}
