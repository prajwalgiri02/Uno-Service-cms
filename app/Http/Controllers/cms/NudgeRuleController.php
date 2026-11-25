<?php

namespace App\Http\Controllers\cms;

use App\Http\Controllers\Controller;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NudgeRuleController extends Controller
{
    private const KEYS = [
        'daily_nudge_cap', 'same_vendor_gap_days', 'walking_min_m', 'walking_max_m', 'trigger_min_m', 'no_wait_minutes',
        'auto_pause_reports', 'combined_nudge', 'weekly_summary_fallback', 'vendor_confirmation_required', 'free_windows_only',
    ];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('cms/nudge-rules/index', [
            'rules' => array_intersect_key($this->settings->all(), array_flip(self::KEYS)),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'daily_nudge_cap' => ['required', 'integer', 'between:1,5'],
            'same_vendor_gap_days' => ['required', 'integer', 'between:1,60'],
            'walking_min_m' => ['required', 'integer', 'between:100,2000'],
            'walking_max_m' => ['required', 'integer', 'between:100,3000', 'gte:walking_min_m'],
            'trigger_min_m' => ['required', 'integer', 'between:50,1000', 'lte:walking_min_m'],
            'no_wait_minutes' => ['required', 'integer', 'between:10,240'],
            'auto_pause_reports' => ['required', 'integer', 'between:1,20'],
            'combined_nudge' => ['required', 'boolean'],
            'weekly_summary_fallback' => ['required', 'boolean'],
            'vendor_confirmation_required' => ['required', 'boolean'],
            'free_windows_only' => ['required', 'boolean'],
        ], [
            'walking_max_m.gte' => 'The far end of the walking distance must be at least the near end.',
            'trigger_min_m.lte' => 'The trigger distance cannot be larger than the walking distance.',
        ]);

        $this->settings->update($data);

        return back()->with('success', 'Nudge rules saved. They apply right away.');
    }
}
