<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BlockedPlace;
use App\Models\DeviceToken;
use App\Models\FreeWindow;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ProfileService
{
    public const DEFAULT_PLACES = ['home' => 'home', 'work' => 'work'];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * @return Collection<int, FreeWindow>
     */
    public function freeWindows(User $user): Collection
    {
        if (! $user->freeWindows()->exists()) {
            foreach (FreeWindow::PRESETS as $key => $preset) {
                $user->freeWindows()->create([
                    'preset' => $key,
                    'title' => $preset['title'],
                    'weekdays' => $preset['weekdays'],
                    'from_time' => $preset['from'].':00',
                    'to_time' => $preset['to'].':00',
                    'enabled' => $preset['enabled'],
                ]);
            }
        }

        return $user->freeWindows()->get();
    }

    /**
     * @param  array{title?: string, weekdays?: list<int>, from?: string, to?: string, enabled?: bool}  $data
     */
    public function saveWindow(User $user, array $data, ?FreeWindow $window = null): FreeWindow
    {
        $attributes = array_filter([
            'title' => $data['title'] ?? null,
            'weekdays' => isset($data['weekdays']) ? array_values(array_unique(array_map('intval', $data['weekdays']))) : null,
            'from_time' => isset($data['from']) ? Carbon::createFromFormat('H:i', $data['from'])->format('H:i:s') : null,
            'to_time' => isset($data['to']) ? Carbon::createFromFormat('H:i', $data['to'])->format('H:i:s') : null,
            'enabled' => $data['enabled'] ?? null,
        ], fn ($value) => $value !== null);

        if ($window) {
            $window->fill($attributes)->save();

            return $window;
        }

        return $user->freeWindows()->create(['enabled' => true, ...$attributes]);
    }

    /**
     * @return Collection<int, BlockedPlace>
     */
    public function blockedPlaces(User $user): Collection
    {
        foreach (self::DEFAULT_PLACES as $kind => $label) {
            $user->blockedPlaces()->firstOrCreate(['kind' => $kind], ['label' => $label, 'enabled' => true]);
        }

        return $user->blockedPlaces()->orderByRaw("CASE kind WHEN 'home' THEN 0 WHEN 'work' THEN 1 ELSE 2 END")->orderBy('id')->get();
    }

    public function pauseToday(User $user): User
    {
        $user->forceFill(['paused_until' => today()->addDay()])->save();

        return $user;
    }

    public function resume(User $user): User
    {
        $user->forceFill(['paused_until' => null])->save();

        return $user;
    }

    /**
     * @param  array{device_id: string, token: string, platform?: string|null, notifications_allowed?: bool, location_permission?: string|null}  $data
     */
    public function registerDevice(User $user, array $data): DeviceToken
    {
        DeviceToken::query()->where('device_id', $data['device_id'])->where('user_id', '!=', $user->id)->delete();

        return $user->deviceTokens()->updateOrCreate(
            ['device_id' => $data['device_id']],
            [
                'token' => $data['token'],
                'platform' => $data['platform'] ?? null,
                'notifications_allowed' => $data['notifications_allowed'] ?? true,
                'location_permission' => $data['location_permission'] ?? null,
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * Server-side view of whether nudges can reach this user.
     *
     * @return array{healthy: bool, checks: list<array{key: string, ok: bool, message: string}>}
     */
    public function alertHealth(User $user): array
    {
        $device = $user->deviceTokens()->latest('last_seen_at')->first();
        $windows = $this->freeWindows($user)->where('enabled', true)->count();

        $checks = [
            ['key' => 'nudges_on', 'ok' => $user->nudges_enabled && ! $user->isPaused(), 'message' => $user->isPaused() ? 'paused for today · back tomorrow' : ($user->nudges_enabled ? 'nudges are on' : 'nudges are off')],
            ['key' => 'device', 'ok' => $device !== null, 'message' => $device ? 'this phone can get alerts' : 'open the app once so we can reach this phone'],
            ['key' => 'notifications', 'ok' => (bool) $device?->notifications_allowed, 'message' => $device?->notifications_allowed ? 'notifications allowed' : 'notifications are blocked in phone settings'],
            ['key' => 'location', 'ok' => $device?->location_permission === 'always', 'message' => $device?->location_permission === 'always' ? 'location allowed all the time' : 'allow location "all the time" so we can tell when you are close'],
            ['key' => 'windows', 'ok' => $windows > 0 || ! $this->settings->bool('free_windows_only'), 'message' => $windows > 0 ? "{$windows} free-time windows set" : 'pick at least one free-time window'],
            ['key' => 'errands', 'ok' => $user->errands()->watching()->exists(), 'message' => $user->errands()->watching()->exists() ? 'your list has things to watch' : 'add something to your list'],
        ];

        return [
            'healthy' => collect($checks)->every(fn (array $check) => $check['ok']),
            'checks' => $checks,
        ];
    }
}
