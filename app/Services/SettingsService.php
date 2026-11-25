<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Platform rules editable from the CMS without an app update.
 */
class SettingsService
{
    private const CACHE_KEY = 'settings.all';

    public const DEFAULTS = [
        'daily_nudge_cap' => 2,
        'same_vendor_gap_days' => 7,
        'walking_min_m' => 200,
        'walking_max_m' => 500,
        'trigger_min_m' => 150,
        'no_wait_minutes' => 45,
        'auto_pause_reports' => 2,
        'combined_nudge' => true,
        'weekly_summary_fallback' => true,
        'vendor_confirmation_required' => true,
        'free_windows_only' => true,
        'points_per_visit' => 20,
        'perk_at' => 200,
        'rewards_open' => false,
    ];

    /**
     * @return array<string, int|bool>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public function get(string $key): int|bool
    {
        return $this->all()[$key];
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    /**
     * @param  array<string, int|bool>  $values
     */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }
}
