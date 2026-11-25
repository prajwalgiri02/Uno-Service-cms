<?php

namespace App\Http\Controllers\cms;

use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\ShopStatusLog;
use App\Services\ShopStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VendorController extends Controller
{
    public function __construct(
        private readonly ShopStatusService $shopStatus,
    ) {}

    public function index(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(ShopStatus::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $status = ShopStatus::from($data['status'] ?? ShopStatus::PENDING->value);
        $search = trim($data['search'] ?? '');
        $perPage = $this->resolvePerPage($request);

        $statuses = $status === ShopStatus::PENDING ? [ShopStatus::PENDING, ShopStatus::CHANGES_REQUESTED] : [$status];

        $shops = Shop::query()
            ->with(['category.parent', 'owner:id,phone'])
            ->whereIn('status', $statuses)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('area', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->orderByRaw('COALESCE(submitted_at, created_at) asc')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Shop $shop) => [
                'id' => $shop->id,
                'name' => $shop->name,
                'category' => $shop->category?->fullName(),
                'area' => $shop->area,
                'phone' => $shop->phone,
                'status' => $shop->status->value,
                'status_label' => $shop->status->label(),
                'submitted_at' => ($shop->submitted_at ?? $shop->created_at)?->toIso8601String(),
                'auto_paused' => $shop->auto_paused_at !== null,
            ]);

        $counts = Shop::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('cms/vendors/index', [
            'shops' => $shops,
            'filters' => ['status' => $status->value, 'search' => $search],
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0) + (int) ($counts['changes_requested'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'paused' => (int) ($counts['paused'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
        ]);
    }

    public function show(Shop $shop): Response
    {
        $shop->load(['category.parent', 'services', 'photos', 'owner:id,name,phone', 'statusLogs.user:id,name']);

        return Inertia::render('cms/vendors/show', [
            'shop' => [
                'id' => $shop->id,
                'name' => $shop->name,
                'category' => $shop->category?->fullName(),
                'phone' => $shop->phone,
                'owner' => $shop->owner?->displayName(),
                'owner_phone' => $shop->owner?->phone,
                'area' => $shop->area,
                'landmark' => $shop->landmark,
                'latitude' => $shop->latitude,
                'longitude' => $shop->longitude,
                'opening_hours' => $shop->opening_hours,
                'women_run' => $shop->women_run,
                'status' => $shop->status->value,
                'status_label' => $shop->status->label(),
                'status_note' => $shop->status_note,
                'submitted_at' => ($shop->submitted_at ?? $shop->created_at)?->toIso8601String(),
                'checklist' => [
                    'check_photos' => $shop->check_photos,
                    'check_pin' => $shop->check_pin,
                    'check_phone' => $shop->check_phone,
                    'check_prices' => $shop->check_prices,
                ],
                'photos' => $shop->photos->map(fn ($photo) => ['id' => $photo->id, 'url' => $photo->url()]),
                'services' => $shop->services->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'minutes' => $s->minutes, 'for_who' => $s->for_who,
                    'price_min' => $s->price_min, 'price_max' => $s->price_max,
                ]),
                'check_ins' => $shop->checkIns()->count(),
                'open_reports' => $shop->reports()->where('status', 'open')->count(),
                'history' => $shop->statusLogs->map(fn (ShopStatusLog $log) => [
                    'id' => $log->id,
                    'to' => ShopStatus::tryFrom($log->to_status)?->label() ?? $log->to_status,
                    'by' => $log->user?->name ?? 'System',
                    'note' => $log->note,
                    'at' => $log->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }

    public function checklist(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'check_photos' => ['sometimes', 'boolean'],
            'check_pin' => ['sometimes', 'boolean'],
            'check_phone' => ['sometimes', 'boolean'],
            'check_prices' => ['sometimes', 'boolean'],
        ]);

        $shop->forceFill($data)->save();

        return back();
    }

    public function decide(Request $request, Shop $shop): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['approve', 'request_changes', 'reject', 'pause', 'restore'])],
            'note' => [Rule::requiredIf(in_array($request->input('action'), ['request_changes', 'reject', 'pause'], true)), 'nullable', 'string', 'max:1000'],
        ]);

        [$to, $message] = match ($data['action']) {
            'approve' => [ShopStatus::APPROVED, "{$shop->name} is approved and live."],
            'request_changes' => [ShopStatus::CHANGES_REQUESTED, "Changes requested from {$shop->name}."],
            'reject' => [ShopStatus::REJECTED, "{$shop->name} was rejected."],
            'pause' => [ShopStatus::PAUSED, "{$shop->name} is paused."],
            'restore' => [ShopStatus::APPROVED, "{$shop->name} is live again."],
        };

        $allowed = match ($data['action']) {
            'approve', 'request_changes', 'reject' => in_array($shop->status, [ShopStatus::PENDING, ShopStatus::CHANGES_REQUESTED, ShopStatus::REJECTED], true),
            'pause' => $shop->status === ShopStatus::APPROVED,
            'restore' => $shop->status === ShopStatus::PAUSED,
        };

        if (! $allowed) {
            return back()->with('error', "This vendor is {$shop->status->label()}, so that action is not available.");
        }

        $this->shopStatus->change($shop, $to, $request->user(), $data['note'] ?? null);

        if (in_array($to, [ShopStatus::PAUSED, ShopStatus::REJECTED], true)) {
            $shop->forceFill(['open_today_on' => null, 'no_wait_until' => null])->save();
        }

        if ($data['action'] === 'restore') {
            $shop->forceFill(['auto_paused_at' => null])->save();
        }

        return redirect()->route('cms.vendor-approvals.show', $shop)->with('success', $message);
    }
}
