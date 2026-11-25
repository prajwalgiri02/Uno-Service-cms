<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ReportStatus;
use App\Models\Nudge;
use App\Models\Shop;
use App\Models\ShopView;
use Illuminate\Support\Carbon;

class VendorInsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function today(Shop $shop): array
    {
        $weekStart = now()->startOfWeek(Carbon::SUNDAY);
        $bars = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = today()->subDays($i);
            $bars[] = [
                'date' => $day->toDateString(),
                'weekday' => $day->dayOfWeekIso,
                'visits' => $shop->checkIns()->whereBetween('created_at', [$day, $day->copy()->endOfDay()])->count(),
            ];
        }

        return [
            'visits_today' => $shop->checkIns()->where('created_at', '>=', today())->count(),
            'nudges_today' => $this->nudgeCount($shop, today()),
            'check_ins_this_week' => $shop->checkIns()->where('created_at', '>=', $weekStart)->count(),
            'last_7_days' => $bars,
        ];
    }

    /**
     * Reminder → page view → visit funnel for today, this week or this month.
     *
     * @return array<string, mixed>
     */
    public function funnel(Shop $shop, string $period): array
    {
        $since = match ($period) {
            'today' => today(),
            'month' => now()->startOfMonth(),
            default => now()->startOfWeek(Carbon::SUNDAY),
        };

        $checkIns = $shop->checkIns()->where('created_at', '>=', $since);
        $hours = (clone $checkIns)->pluck('created_at')->countBy(fn (Carbon $at) => $at->hour);
        $bestHour = $hours->isEmpty() ? null : (int) $hours->sortDesc()->keys()->first();

        return [
            'period' => $period,
            'people_reminded' => $this->nudgeCount($shop, $since),
            'opened_shop_page' => ShopView::query()->where('shop_id', $shop->id)->where('created_at', '>=', $since)->distinct()->count('user_id'),
            'checked_in' => (clone $checkIns)->count(),
            'new_customers' => (clone $checkIns)->where('first_visit', true)->count(),
            'best_time' => $bestHour === null ? null : Carbon::createFromTime($bestHour)->format('g').'–'.Carbon::createFromTime($bestHour + 1)->format('g a'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function reviewSummary(Shop $shop): array
    {
        $visits = $shop->checkIns()->where('created_at', '>=', now()->subDays(90))->count();
        $reports = $shop->reports()->where('created_at', '>=', now()->subDays(90))->where(fn ($q) => $q
            ->where('status', ReportStatus::OPEN)
            ->orWhereIn('resolution', ['warned', 'vendor_paused']))->count();

        return [
            'rating' => round((float) $shop->reviews()->avg('stars'), 1),
            'rating_count' => $shop->reviews()->count(),
            'reliability_score' => $visits === 0 && $reports === 0 ? 100 : (int) round(100 * max(0, 1 - $reports / max($visits, 1))),
            'visits_90_days' => $visits,
            'needs_reply' => $shop->reviews()->where('stars', '<=', 2)->whereNull('reply')->count(),
        ];
    }

    private function nudgeCount(Shop $shop, Carbon $since): int
    {
        return Nudge::query()
            ->where('created_at', '>=', $since)
            ->whereHas('shops', fn ($q) => $q->whereKey($shop->id))
            ->count();
    }
}
