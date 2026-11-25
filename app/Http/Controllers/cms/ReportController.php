<?php

namespace App\Http\Controllers\cms;

use App\Enums\ReportResolution;
use App\Enums\ReportStatus;
use App\Enums\ShopStatus;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\Shop;
use App\Services\ReportService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::enum(ReportStatus::class)],
            'search' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        $status = $data['status'] ?? ReportStatus::OPEN->value;
        $search = trim($data['search'] ?? '');

        $openCounts = Report::query()->where('status', ReportStatus::OPEN)->selectRaw('shop_id, count(*) as total')->groupBy('shop_id')->pluck('total', 'shop_id');

        $reports = Report::query()
            ->with(['shop:id,name,status,auto_paused_at', 'user:id,name,phone', 'resolver:id,name'])
            ->where('status', $status)
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $inner) => $inner
                ->whereHas('shop', fn (Builder $s) => $s->where('name', 'like', "%{$search}%"))
                ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate($this->resolvePerPage($request))
            ->withQueryString()
            ->through(fn (Report $report) => [
                'id' => $report->id,
                'shop_id' => $report->shop_id,
                'vendor' => $report->shop?->name,
                'reason' => $report->reason->label(),
                'note' => $report->note,
                'reported_by' => $report->user?->displayName(),
                'created_at' => $report->created_at->toIso8601String(),
                'reports' => (int) ($openCounts[$report->shop_id] ?? 0),
                'vendor_status' => match (true) {
                    $report->shop?->status === ShopStatus::PAUSED && $report->shop->auto_paused_at !== null => 'auto_paused',
                    $report->shop?->status === ShopStatus::PAUSED => 'paused',
                    $report->status === ReportStatus::OPEN => 'under_review',
                    default => 'active',
                },
                'resolution' => $report->resolution?->label(),
                'resolution_note' => $report->resolution_note,
                'resolved_by' => $report->resolver?->name,
                'resolved_at' => $report->resolved_at?->toIso8601String(),
            ]);

        $resolved = Report::query()->whereNotNull('resolved_at')->where('resolved_at', '>=', now()->subDays(30))->get(['created_at', 'resolved_at']);

        return Inertia::render('cms/reports/index', [
            'reports' => $reports,
            'filters' => ['status' => $status, 'search' => $search],
            'stats' => [
                'open' => Report::query()->where('status', ReportStatus::OPEN)->count(),
                'auto_paused' => Shop::query()->where('status', ShopStatus::PAUSED)->whereNotNull('auto_paused_at')->count(),
                'avg_resolution_hours' => $resolved->isEmpty() ? null : round($resolved->avg(fn (Report $r) => $r->created_at->diffInMinutes($r->resolved_at)) / 60, 1),
            ],
            'resolutions' => collect(ReportResolution::cases())->map(fn (ReportResolution $r) => ['value' => $r->value, 'label' => $r->label()]),
        ]);
    }

    public function resolve(Request $request, Report $report, ReportService $reports): RedirectResponse
    {
        $data = $request->validate([
            'resolution' => ['required', Rule::enum(ReportResolution::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($report->status !== ReportStatus::OPEN) {
            return back()->with('error', 'This report is already resolved.');
        }

        $reports->resolve($report, $request->user(), ReportResolution::from($data['resolution']), $data['note'] ?? null);

        return back()->with('success', "Reports for {$report->shop->name} resolved.");
    }
}
