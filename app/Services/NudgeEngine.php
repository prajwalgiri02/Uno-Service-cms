<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NudgeResult;
use App\Models\Errand;
use App\Models\Nudge;
use App\Models\Shop;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Decides whether a user at a location should get a nudge right now.
 * Every gate that fails keeps Najik silent and reports why.
 */
class NudgeEngine
{
    public const SENT = 'sent';

    public const NUDGES_OFF = 'nudges_off';

    public const PAUSED = 'paused';

    public const OUTSIDE_FREE_WINDOW = 'outside_free_window';

    public const AT_BLOCKED_PLACE = 'at_blocked_place';

    public const DAILY_CAP = 'daily_cap';

    public const WEEKLY_SUMMARY = 'weekly_summary';

    public const NO_ERRANDS = 'no_errands';

    public const NO_MATCH = 'no_match';

    private const UNOPENED_STREAK_DAYS = 14;

    private const UNOPENED_STREAK_MIN = 3;

    public function __construct(
        private readonly SettingsService $settings,
        private readonly ShopFinder $finder,
        private readonly PushService $push,
    ) {}

    /**
     * @return array{nudge: Nudge|null, reason: string}
     */
    public function check(User $user, float $lat, float $lng): array
    {
        return DB::transaction(function () use ($user, $lat, $lng) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $now = now();

            $reason = $this->gateReason($user, $lat, $lng, $now);

            if ($reason !== null) {
                return ['nudge' => null, 'reason' => $reason];
            }

            $errands = $user->errands()->watching()->with('category.children')->get();

            if ($errands->isEmpty()) {
                return ['nudge' => null, 'reason' => self::NO_ERRANDS];
            }

            $matches = $this->matches($user, $errands, $lat, $lng, $now);

            if ($matches->isEmpty()) {
                return ['nudge' => null, 'reason' => self::NO_MATCH];
            }

            $nudge = $this->createNudge($user, $matches);

            return ['nudge' => $nudge, 'reason' => self::SENT];
        });
    }

    public function dailyCap(User $user): int
    {
        return max(0, min($user->pings_per_day, $this->settings->int('daily_nudge_cap')));
    }

    public function sentToday(User $user): int
    {
        return $user->nudges()->where('created_at', '>=', today())->count();
    }

    public function markOpened(Nudge $nudge): Nudge
    {
        if ($nudge->opened_at === null) {
            $nudge->forceFill([
                'opened_at' => now(),
                'result' => $nudge->result === NudgeResult::VISITED ? NudgeResult::VISITED : NudgeResult::OPENED,
            ])->save();
        }

        return $nudge;
    }

    private function gateReason(User $user, float $lat, float $lng, Carbon $now): ?string
    {
        if (! $user->nudges_enabled) {
            return self::NUDGES_OFF;
        }

        if ($user->isPaused()) {
            return self::PAUSED;
        }

        if ($this->settings->bool('free_windows_only') && ! $user->freeWindows()->get()->contains(fn ($w) => $w->covers($now))) {
            return self::OUTSIDE_FREE_WINDOW;
        }

        $atBlockedPlace = $user->blockedPlaces()
            ->where('enabled', true)
            ->whereNotNull('latitude')
            ->get()
            ->contains(fn ($place) => Geo::distanceM($lat, $lng, $place->latitude, $place->longitude) <= $place->radius_m);

        if ($atBlockedPlace) {
            return self::AT_BLOCKED_PLACE;
        }

        if ($this->sentToday($user) >= $this->dailyCap($user)) {
            return self::DAILY_CAP;
        }

        if ($this->settings->bool('weekly_summary_fallback') && $this->ignoresNudges($user)) {
            return self::WEEKLY_SUMMARY;
        }

        return null;
    }

    public function ignoresNudges(User $user): bool
    {
        $recent = $user->nudges()->where('created_at', '>=', now()->subDays(self::UNOPENED_STREAK_DAYS));

        return (clone $recent)->count() >= self::UNOPENED_STREAK_MIN
            && ! (clone $recent)->whereNotNull('opened_at')->exists();
    }

    /**
     * Closest eligible shop per errand, closest first, one errand per shop.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Errand>  $errands
     * @return Collection<int, array{errand: Errand, shop: Shop}>
     */
    private function matches(User $user, $errands, float $lat, float $lng, Carbon $now): Collection
    {
        $minM = $this->settings->int('walking_min_m');
        $maxM = $this->settings->int('walking_max_m');
        $categoryIds = $errands->flatMap(fn (Errand $e) => $e->category->matchingIds())->unique()->values()->all();

        $recentlyNudged = $user->nudges()
            ->where('created_at', '>=', $now->copy()->subDays($this->settings->int('same_vendor_gap_days')))
            ->pluck('shop_ids')
            ->flatten()
            ->all();

        $requireConfirmation = $this->settings->bool('vendor_confirmation_required');

        $shops = $this->finder->near($lat, $lng, $maxM, $categoryIds)
            ->reject(fn (Shop $shop) => in_array($shop->id, $recentlyNudged, true))
            ->filter(fn (Shop $shop) => (! $requireConfirmation || $shop->isOpenToday()) && $shop->isWithinHours($now));

        $usedShops = [];

        return $errands
            ->map(function (Errand $errand) use ($shops, $minM, $maxM, $now) {
                $radius = min(max($errand->radius_m, $minM), $maxM);
                $ids = $errand->category->matchingIds();

                $shop = $shops->first(fn (Shop $shop) => in_array($shop->category_id, $ids, true)
                    && $shop->distance_m <= $radius
                    && ($errand->max_price === null || ($shop->priceMin() ?? 0) <= $errand->max_price)
                    && (! $errand->only_no_wait || $shop->noWaitActive())
                    && (! $errand->offers_only || ($shop->currentOffer?->isRunningAt($now) ?? false))
                    && (! $errand->women_run || $shop->women_run));

                return $shop ? ['errand' => $errand, 'shop' => $shop] : null;
            })
            ->filter()
            ->sortBy(fn (array $match) => $match['shop']->distance_m)
            ->filter(function (array $match) use (&$usedShops) {
                if (in_array($match['shop']->id, $usedShops, true)) {
                    return false;
                }
                $usedShops[] = $match['shop']->id;

                return true;
            })
            ->values()
            ->toBase();
    }

    /**
     * @param  Collection<int, array{errand: Errand, shop: Shop}>  $matches
     */
    private function createNudge(User $user, Collection $matches): Nudge
    {
        $picked = $this->settings->bool('combined_nudge') ? $matches->take(2) : $matches->take(1);
        $first = $picked->first();
        $farthest = $picked->max(fn (array $m) => $m['shop']->distance_m);

        if ($picked->count() > 1) {
            $title = "got 20 min? {$picked->count()} errands, 1 trip";
            $body = $picked->map(fn (array $m) => $m['errand']->title)->join(' + ')." are both within {$farthest} m. both open now.";
        } else {
            $shop = $first['shop'];
            $title = "{$first['errand']->title} · {$shop->distance_m} m away";
            $extras = array_filter([
                $shop->noWaitActive() ? 'no wait rn' : null,
                $shop->currentOffer?->label(),
            ]);
            $body = "{$shop->name} is open now".($extras ? ' · '.implode(' · ', $extras) : '').'.';
        }

        $nudge = $user->nudges()->create([
            'shop_id' => $first['shop']->id,
            'shop_ids' => $picked->map(fn (array $m) => $m['shop']->id)->values()->all(),
            'errand_ids' => $picked->map(fn (array $m) => $m['errand']->id)->values()->all(),
            'distance_m' => $first['shop']->distance_m,
            'title' => $title,
            'body' => $body,
            'result' => NudgeResult::SENT,
        ]);

        $nudge->shops()->attach($nudge->shop_ids);

        $nudge->forceFill([
            'push_delivered' => $this->push->toUser($user, $title, $body, ['type' => 'nudge', 'nudge_id' => (string) $nudge->id]),
        ])->save();

        return $nudge;
    }
}
