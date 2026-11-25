<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class CustomerStatsService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return array<string, int|bool>
     */
    public function stats(User $user): array
    {
        $monthStart = now()->startOfMonth();
        $perkAt = $this->settings->int('perk_at');

        $doneThisMonth = $user->errands()->where('done_at', '>=', $monthStart)->count();
        $monthCheckIns = $user->checkIns()->where('created_at', '>=', $monthStart);

        return [
            'points' => $user->points_balance,
            'perk_at' => $perkAt,
            'points_to_next_perk' => max(0, $perkAt - $user->points_balance),
            'perk_unlocked' => $user->points_balance >= $perkAt,
            'rewards_open' => $this->settings->bool('rewards_open'),
            'streak_weeks' => $this->streakWeeks($user),
            'errands_done_this_month' => $doneThisMonth,
            'errands_goal_this_month' => $doneThisMonth + $user->errands()->open()->count(),
            'visits_this_month' => (clone $monthCheckIns)->count(),
            'saved_rs_this_month' => (int) (clone $monthCheckIns)->sum('saved_rs'),
            'no_wait_minutes_saved_this_month' => (int) (clone $monthCheckIns)->sum('minutes_saved'),
        ];
    }

    /**
     * Consecutive weeks (Sunday to Saturday) with at least one check-in, counting
     * back from this week, or from last week while this week has none yet.
     */
    public function streakWeeks(User $user): int
    {
        $weeks = $user->checkIns()
            ->where('created_at', '>=', now()->subWeeks(60))
            ->pluck('created_at')
            ->map(fn (Carbon $at) => $at->copy()->startOfWeek(Carbon::SUNDAY)->toDateString())
            ->unique()
            ->flip();

        $cursor = now()->startOfWeek(Carbon::SUNDAY);

        if (! $weeks->has($cursor->toDateString())) {
            $cursor->subWeek();
        }

        $streak = 0;

        while ($weeks->has($cursor->toDateString())) {
            $streak++;
            $cursor->subWeek();
        }

        return $streak;
    }

    /**
     * @return Collection<int, CheckIn>
     */
    public function visitsInMonth(User $user, Carbon $month)
    {
        return $user->checkIns()
            ->with(['shop.category', 'review'])
            ->whereBetween('created_at', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
            ->latest()
            ->get();
    }
}
