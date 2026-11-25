<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CheckInMethod;
use App\Enums\NudgeResult;
use App\Models\CheckIn;
use App\Models\Errand;
use App\Models\Nudge;
use App\Models\PointTransaction;
use App\Models\Review;
use App\Models\Shop;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInService
{
    public const MAX_DISTANCE_M = 500;

    public const VISIT_CODE_MINUTES = 10;

    public const NO_WAIT_MINUTES_SAVED = 12;

    private const NUDGE_LINK_HOURS = 6;

    private const REVIEW_WINDOW_DAYS = 7;

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function findShopByCode(string $code): ?Shop
    {
        $digits = preg_replace('/\D+/', '', $code);

        if ($digits === '') {
            return null;
        }

        return Shop::query()->live()->where('check_in_code', 'NJK-'.$digits)->first();
    }

    /**
     * Customer types (or scans) the code shown on the shop counter.
     *
     * @throws ValidationException
     */
    public function checkInWithShopCode(User $user, string $code, float $lat, float $lng, ?int $expectedShopId = null): CheckIn
    {
        $shop = $this->findShopByCode($code);

        if (! $shop || ($expectedShopId !== null && $shop->id !== $expectedShopId)) {
            throw ValidationException::withMessages(['code' => "That code doesn't match. Ask at the counter."]);
        }

        if (Geo::distanceM($lat, $lng, $shop->latitude, $shop->longitude) > self::MAX_DISTANCE_M) {
            throw ValidationException::withMessages(['code' => 'You need to be at the shop to check in.']);
        }

        return $this->record($user, $shop, CheckInMethod::SHOP_CODE);
    }

    /**
     * A short-lived code the customer shows when the shop confirms the visit instead.
     */
    public function visitCode(User $user): string
    {
        $existing = Cache::get("visit-code-user:{$user->id}");

        if ($existing && Cache::get("visit-code:{$existing}") === $user->id) {
            return $existing;
        }

        do {
            $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Cache::has("visit-code:{$code}"));

        $ttl = now()->addMinutes(self::VISIT_CODE_MINUTES);
        Cache::put("visit-code:{$code}", $user->id, $ttl);
        Cache::put("visit-code-user:{$user->id}", $code, $ttl);

        return $code;
    }

    /**
     * @throws ValidationException
     */
    public function confirmWithCustomerCode(Shop $shop, string $code): CheckIn
    {
        $userId = Cache::get("visit-code:{$code}");
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            throw ValidationException::withMessages(['code' => "That code doesn't match. Ask the customer to refresh it in their app."]);
        }

        $checkIn = $this->record($user, $shop, CheckInMethod::CUSTOMER_CODE);

        Cache::forget("visit-code:{$code}");
        Cache::forget("visit-code-user:{$user->id}");

        return $checkIn;
    }

    /**
     * @throws ValidationException
     */
    public function record(User $user, Shop $shop, CheckInMethod $method): CheckIn
    {
        if (! $shop->isLive()) {
            throw ValidationException::withMessages(['code' => 'This shop is not taking check-ins right now.']);
        }

        if ($shop->user_id === $user->id) {
            throw ValidationException::withMessages(['code' => "You can't check in at your own shop."]);
        }

        return DB::transaction(function () use ($user, $shop, $method) {
            User::query()->whereKey($user->id)->lockForUpdate()->first();

            $already = CheckIn::query()
                ->where('user_id', $user->id)
                ->where('shop_id', $shop->id)
                ->where('created_at', '>=', today())
                ->exists();

            if ($already) {
                throw ValidationException::withMessages(['code' => 'You already checked in here today.']);
            }

            $points = $this->settings->int('points_per_visit');
            $offer = $shop->currentOffer;
            $nudge = $this->recentNudge($user, $shop);

            $checkIn = CheckIn::query()->create([
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'nudge_id' => $nudge?->id,
                'method' => $method,
                'points' => $points,
                'saved_rs' => $offer?->isRunningAt() ? (int) round(($shop->priceMin() ?? 0) * $offer->percent / 100) : 0,
                'minutes_saved' => $shop->noWaitActive() ? self::NO_WAIT_MINUTES_SAVED : 0,
                'first_visit' => ! CheckIn::query()->where('user_id', $user->id)->where('shop_id', $shop->id)->exists(),
            ]);

            PointTransaction::query()->create([
                'user_id' => $user->id,
                'check_in_id' => $checkIn->id,
                'points' => $points,
                'reason' => PointTransaction::REASON_CHECK_IN,
            ]);

            User::query()->whereKey($user->id)->increment('points_balance', $points);

            $nudge?->forceFill(['result' => NudgeResult::VISITED, 'visited_at' => now()])->save();

            $this->completeErrands($user, $shop);

            return $checkIn->load('shop.category');
        });
    }

    /**
     * @throws ValidationException
     */
    public function review(User $user, CheckIn $checkIn, int $stars, ?string $text): Review
    {
        if ($checkIn->user_id !== $user->id) {
            abort(404);
        }

        if ($checkIn->review()->exists()) {
            throw ValidationException::withMessages(['stars' => 'You already rated this visit.']);
        }

        if ($checkIn->created_at->lt(now()->subDays(self::REVIEW_WINDOW_DAYS))) {
            throw ValidationException::withMessages(['stars' => 'Visits can be rated for '.self::REVIEW_WINDOW_DAYS.' days.']);
        }

        return Review::query()->create([
            'check_in_id' => $checkIn->id,
            'user_id' => $user->id,
            'shop_id' => $checkIn->shop_id,
            'stars' => $stars,
            'text' => $text,
        ]);
    }

    private function recentNudge(User $user, Shop $shop): ?Nudge
    {
        return $user->nudges()
            ->where('created_at', '>=', now()->subHours(self::NUDGE_LINK_HOURS))
            ->whereNull('visited_at')
            ->latest()
            ->get()
            ->first(fn (Nudge $nudge) => in_array($shop->id, $nudge->shop_ids, true));
    }

    private function completeErrands(User $user, Shop $shop): void
    {
        $user->errands()->open()->with('category.children')->get()
            ->filter(fn (Errand $errand) => in_array($shop->category_id, $errand->category->matchingIds(), true))
            ->each(fn (Errand $errand) => $errand->forceFill(['done_at' => now(), 'active' => false])->save());
    }
}
