<?php

namespace App\Http\Controllers\cms;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\CheckIn;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $data = $request->validate(['search' => ['sometimes', 'nullable', 'string', 'max:80']]);
        $search = trim($data['search'] ?? '');

        $monthStart = now()->startOfMonth();
        $lastMonth = now()->subMonthNoOverflow()->startOfMonth();
        $issued = (int) PointTransaction::query()->where('points', '>', 0)->sum('points');
        $outstanding = (int) User::query()->sum('points_balance');
        $checkInsThisMonth = CheckIn::query()->where('created_at', '>=', $monthStart)->count();
        $checkInsLastMonth = CheckIn::query()->whereBetween('created_at', [$lastMonth, $lastMonth->copy()->addDays(now()->day - 1)->endOfDay()])->count();

        $users = User::query()
            ->whereHas('roles', fn (Builder $q) => $q->where('name', UserRole::CUSTOMER->value))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->withCount('checkIns')
            ->withSum(['pointTransactions as points_earned' => fn (Builder $q) => $q->where('points', '>', 0)], 'points')
            ->withMax('checkIns', 'created_at')
            ->orderByDesc('points_balance')
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->displayName(),
                'phone' => $user->phone,
                'visits' => $user->check_ins_count,
                'points_earned' => (int) $user->points_earned,
                'balance' => $user->points_balance,
                'last_check_in' => $user->check_ins_max_created_at,
            ]);

        return Inertia::render('cms/loyalty/index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'stats' => [
                'issued' => $issued,
                'issued_this_week' => (int) PointTransaction::query()->where('points', '>', 0)->where('created_at', '>=', now()->subDays(7))->sum('points'),
                'outstanding' => $outstanding,
                'unspent_percent' => $issued > 0 ? (int) round($outstanding / $issued * 100) : null,
                'check_ins_this_month' => $checkInsThisMonth,
                'check_ins_change' => $checkInsLastMonth > 0 ? round(($checkInsThisMonth - $checkInsLastMonth) / $checkInsLastMonth * 100, 1) : null,
            ],
            'pointsPerVisit' => $this->settings->int('points_per_visit'),
            'perkAt' => $this->settings->int('perk_at'),
            'rewardsOpen' => $this->settings->bool('rewards_open'),
        ]);
    }

    public function updateRules(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'points_per_visit' => ['required', 'integer', 'between:0,500'],
            'perk_at' => ['required', 'integer', 'between:1,100000'],
        ]);

        $this->settings->update($data);

        return back()->with('success', 'Points rules saved.');
    }

    public function show(User $user): Response
    {
        return Inertia::render('cms/loyalty/show', [
            'member' => [
                'id' => $user->id,
                'name' => $user->displayName(),
                'phone' => $user->phone,
                'area' => $user->area,
                'balance' => $user->points_balance,
                'blocked' => $user->isBlocked(),
                'joined_at' => $user->created_at->toIso8601String(),
                'errands' => $user->errands()->open()->count(),
            ],
            'transactions' => $user->pointTransactions()
                ->with(['checkIn.shop:id,name', 'author:id,name'])
                ->latest()
                ->limit(50)
                ->get()
                ->map(fn (PointTransaction $t) => [
                    'id' => $t->id,
                    'points' => $t->points,
                    'reason' => $t->reason === PointTransaction::REASON_CHECK_IN ? 'Check-in at '.($t->checkIn?->shop?->name ?? 'a shop') : 'Adjustment',
                    'note' => $t->note,
                    'by' => $t->author?->name,
                    'at' => $t->created_at->toIso8601String(),
                ]),
        ]);
    }

    public function adjust(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'between:-10000,10000', 'not_in:0'],
            'note' => ['required', 'string', 'max:255'],
        ]);

        if ($user->points_balance + $data['points'] < 0) {
            return back()->withErrors(['points' => 'The balance cannot go below zero.']);
        }

        DB::transaction(function () use ($user, $data, $request) {
            PointTransaction::query()->create([
                'user_id' => $user->id,
                'points' => $data['points'],
                'reason' => PointTransaction::REASON_ADJUSTMENT,
                'created_by' => $request->user()->id,
                'note' => $data['note'],
            ]);

            $user->increment('points_balance', $data['points']);
        });

        return back()->with('success', 'Points adjusted.');
    }

    public function toggleBlock(Request $request, User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Admins cannot be blocked here.');
        }

        $user->forceFill(['blocked_at' => $user->isBlocked() ? null : now()])->save();

        return back()->with('success', $user->isBlocked() ? 'User blocked. They are signed out of the app.' : 'User unblocked.');
    }
}
