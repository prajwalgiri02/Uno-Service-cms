<?php

namespace App\Http\Controllers\cms;

use App\Enums\NudgeResult;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Nudge;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboard,
    ) {}

    public function index(Request $request): Response
    {
        [$days, $filters] = $this->filters($request);

        return Inertia::render('cms/dashboard/index', [
            'period' => $days,
            'filters' => $filters,
            'stats' => $this->dashboard->stats($days),
            'chart' => $this->dashboard->chart($days),
            'attention' => $this->dashboard->attention($days),
            'nudges' => $this->dashboard->recentNudges($filters)->through(fn (Nudge $nudge) => $this->nudgeRow($nudge)),
            'categories' => Category::query()->whereNotNull('parent_id')->orderBy('name')->get(['id', 'name']),
            'results' => array_map(fn (NudgeResult $r) => $r->value, NudgeResult::cases()),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [$days, $filters] = $this->filters($request);
        $query = $this->dashboard->nudgeQuery($filters, today()->subDays($days - 1))->latest();

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nudge ID', 'Sent at', 'Vendor', 'Category', 'User', 'Distance (m)', 'Result', 'Push delivered']);

            $query->chunk(500, function ($nudges) use ($out) {
                foreach ($nudges as $nudge) {
                    fputcsv($out, [
                        'N-'.$nudge->id,
                        $nudge->created_at->toDateTimeString(),
                        $nudge->shop?->name,
                        $nudge->shop?->category?->name,
                        $nudge->user?->displayName(),
                        $nudge->distance_m,
                        $nudge->result->value,
                        $nudge->push_delivered ? 'yes' : 'no',
                    ]);
                }
            });

            fclose($out);
        }, 'najik-nudges-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: int, 1: array{category_id: int|null, result: string|null}}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'period' => ['sometimes', Rule::in(DashboardService::PERIODS)],
            'category_id' => ['sometimes', 'nullable', 'integer'],
            'result' => ['sometimes', 'nullable', Rule::enum(NudgeResult::class)],
        ]);

        return [
            (int) ($data['period'] ?? 7),
            ['category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null, 'result' => $data['result'] ?? null],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nudgeRow(Nudge $nudge): array
    {
        return [
            'id' => $nudge->id,
            'vendor' => $nudge->shop?->name,
            'category' => $nudge->shop?->category?->name,
            'user' => $nudge->user?->displayName(),
            'distance_m' => $nudge->distance_m,
            'result' => $nudge->result->value,
            'combined' => $nudge->isCombined(),
            'sent_at' => $nudge->created_at->toIso8601String(),
        ];
    }
}
