<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\ShopStatusLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ShopStatusService
{
    public function __construct(
        private readonly PushService $push,
    ) {}

    public function change(Shop $shop, ShopStatus $to, ?User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($shop, $to, $admin, $note) {
            $from = $shop->status;

            $shop->forceFill([
                'status' => $to,
                'status_note' => $note,
                'reviewed_by' => $admin?->id ?? $shop->reviewed_by,
                'reviewed_at' => $admin ? now() : $shop->reviewed_at,
            ])->save();

            ShopStatusLog::query()->create([
                'shop_id' => $shop->id,
                'user_id' => $admin?->id,
                'from_status' => $from?->value,
                'to_status' => $to->value,
                'note' => $note,
            ]);
        });

        $message = match ($to) {
            ShopStatus::APPROVED => 'Your shop is live on Najik. Turn on "open today" to reach people nearby.',
            ShopStatus::CHANGES_REQUESTED => 'Najik asked for a few changes to your shop. Open the app to see them.',
            ShopStatus::REJECTED => 'Your shop could not be approved. Open the app to see why.',
            ShopStatus::PAUSED => 'Your shop is paused on Najik. Open the app to see why.',
            ShopStatus::PENDING => null,
        };

        if ($message && $shop->owner) {
            $this->push->toUser($shop->owner, 'Najik for Shops', $message, ['type' => 'shop_status', 'status' => $to->value]);
        }
    }
}
