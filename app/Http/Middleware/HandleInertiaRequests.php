<?php

namespace App\Http\Middleware;

use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Enums\TicketStatus;
use App\Models\Report;
use App\Models\Shop;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ] : null,
            ],
            'badges' => fn () => $user?->isAdmin() ? [
                'vendor-approvals' => Shop::query()->where('status', ShopStatus::PENDING)->count(),
                'report-queue' => Report::query()->where('status', ReportStatus::OPEN)->count(),
                'support-desk' => SupportTicket::query()->where('status', TicketStatus::OPEN)->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success') ?? $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'flash_id' => fn () => microtime(true),
            ],
        ];
    }
}
