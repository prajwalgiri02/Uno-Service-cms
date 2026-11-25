<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NudgeResult;
use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Enums\TicketStatus;
use App\Models\CheckIn;
use App\Models\Errand;
use App\Models\Nudge;
use App\Models\Report;
use App\Models\Shop;
use App\Models\SupportTicket;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DashboardService
{
    public const PERIODS = [7, 30];

    /**
     * @return array<string, mixed>
     */
    public function stats(int $days): array
    {
        $since = today()->subDays($days - 1);
        $previous = $since->copy()->subDays($days);

        $nudges = Nudge::query()->where('created_at', '>=', $since)->count();
        $previousNudges = Nudge::query()->whereBetween('created_at', [$previous, $since])->count();
        $visited = Nudge::query()->where('created_at', '>=', $since)->where('result', NudgeResult::VISITED)->count();

        return [
            'active_vendors' => Shop::query()->live()->count(),
            'new_vendors' => Shop::query()->live()->where('reviewed_at', '>=', $since)->count(),
            'active_wishlists' => Errand::query()->watching()->count(),
            'new_wishlists' => Errand::query()->where('created_at', '>=', $since)->count(),
            'nudges_sent' => $nudges,
            'nudges_change' => $previousNudges > 0 ? round(($nudges - $previousNudges) / $previousNudges * 100, 1) : null,
            'visit_rate' => $nudges > 0 ? round($visited / $nudges * 100, 1) : null,
            'open_reports' => Report::query()->where('status', ReportStatus::OPEN)->count(),
            'auto_paused' => Shop::query()->where('status', ShopStatus::PAUSED)->whereNotNull('auto_paused_at')->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, nudges: list<int>, check_ins: list<int>}
     */
    public function chart(int $days): array
    {
        $since = today()->subDays($days - 1);

        $nudges = Nudge::query()->where('created_at', '>=', $since)->pluck('created_at')->countBy(fn (Carbon $at) => $at->toDateString());
        $checkIns = CheckIn::query()->where('created_at', '>=', $since)->pluck('created_at')->countBy(fn (Carbon $at) => $at->toDateString());

        $labels = $nudgeSeries = $checkInSeries = [];

        for ($day = $since->copy(); $day->lte(today()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $days <= 7 ? strtoupper($day->format('D')) : $day->format('j M');
            $nudgeSeries[] = $nudges[$key] ?? 0;
            $checkInSeries[] = $checkIns[$key] ?? 0;
        }

        return ['labels' => $labels, 'nudges' => $nudgeSeries, 'check_ins' => $checkInSeries];
    }

    /**
     * @return array<string, int|float|null>
     */
    public function attention(int $days): array
    {
        $since = today()->subDays($days - 1);
        $nudges = Nudge::query()->where('created_at', '>=', $since);
        $total = (clone $nudges)->count();

        return [
            'pending_vendors' => Shop::query()->where('status', ShopStatus::PENDING)->count(),
            'open_reports' => Report::query()->where('status', ReportStatus::OPEN)->count(),
            'open_tickets' => SupportTicket::query()->where('status', TicketStatus::OPEN)->count(),
            'not_confirmed_today' => Shop::query()->live()->where(fn (Builder $q) => $q->whereNull('open_today_on')->orWhereDate('open_today_on', '<', today()))->count(),
            'alert_delivery_rate' => $total > 0 ? round((clone $nudges)->where('push_delivered', true)->count() / $total * 100) : null,
        ];
    }

    /**
     * @param  array{category_id?: int|null, result?: string|null}  $filters
     * @return LengthAwarePaginator<int, Nudge>
     */
    public function recentNudges(array $filters, int $perPage = 10): LengthAwarePaginator
    {
        return $this->nudgeQuery($filters)->latest()->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{category_id?: int|null, result?: string|null}  $filters
     * @return Builder<Nudge>
     */
    public function nudgeQuery(array $filters, ?Carbon $since = null): Builder
    {
        return Nudge::query()
            ->with(['user:id,name,phone', 'shop:id,name,category_id', 'shop.category:id,name'])
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->when($filters['result'] ?? null, fn (Builder $q, string $result) => $q->where('result', $result))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $categoryId) => $q->whereHas('shop', fn (Builder $s) => $s->where('category_id', $categoryId)));
    }
}
