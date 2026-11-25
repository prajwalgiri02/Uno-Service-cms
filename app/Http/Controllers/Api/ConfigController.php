<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Models\FreeWindow;
use App\Services\CheckInService;
use App\Services\ErrandService;
use App\Services\SettingsService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    use ApiResponse;

    public function __invoke(SettingsService $settings): JsonResponse
    {
        $rules = $settings->all();

        return $this->successResponse([
            'nudges' => [
                'daily_cap' => $rules['daily_nudge_cap'],
                'walking_min_m' => $rules['walking_min_m'],
                'walking_max_m' => $rules['walking_max_m'],
                'trigger_min_m' => $rules['trigger_min_m'],
                'free_windows_only' => $rules['free_windows_only'],
            ],
            'points' => [
                'per_visit' => $rules['points_per_visit'],
                'perk_at' => $rules['perk_at'],
                'rewards_open' => $rules['rewards_open'],
            ],
            'no_wait_minutes' => $rules['no_wait_minutes'],
            'check_in_max_distance_m' => CheckInService::MAX_DISTANCE_M,
            'errand_radii_m' => [500, 1000, 2000, 3000],
            'errand_durations' => array_keys(ErrandService::DURATIONS),
            'free_window_presets' => collect(FreeWindow::PRESETS)->map(fn (array $p, string $key) => ['key' => $key, ...$p])->values(),
            'report_reasons' => collect(ReportReason::cases())->map(fn (ReportReason $r) => [
                'value' => $r->value,
                'label' => $r->label(),
                'emoji' => $r->emoji(),
            ]),
            'languages' => ['en', 'ne'],
        ]);
    }
}
