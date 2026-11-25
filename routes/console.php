<?php

use App\Enums\NudgeResult;
use App\Models\Nudge;
use App\Models\User;
use App\Services\NudgeEngine;
use App\Services\OtpService;
use App\Services\PushService;
use App\Services\SettingsService;
use Illuminate\Support\Facades\Artisan;

Artisan::command('otp:prune', function (OtpService $otpService) {
    $this->info("Deleted {$otpService->clearExpired()} expired codes.");
})->purpose('Delete expired one-time codes');

Artisan::command('nudges:mark-ignored', function () {
    $count = Nudge::query()
        ->where('result', NudgeResult::SENT->value)
        ->where('created_at', '<', now()->subDay())
        ->update(['result' => NudgeResult::IGNORED->value]);

    $this->info("Marked {$count} nudges as ignored.");
})->purpose('Mark nudges nobody opened within a day as ignored');

Artisan::command('nudges:weekly-summary', function (SettingsService $settings, NudgeEngine $engine, PushService $push) {
    if (! $settings->bool('weekly_summary_fallback')) {
        return;
    }

    $sent = 0;

    User::query()
        ->where('nudges_enabled', true)
        ->whereHas('errands', fn ($q) => $q->watching())
        ->whereHas('nudges', fn ($q) => $q->where('created_at', '>=', now()->subDays(14)))
        ->chunkById(200, function ($users) use ($engine, $push, &$sent) {
            foreach ($users as $user) {
                if (! $engine->ignoresNudges($user)) {
                    continue;
                }

                $titles = $user->errands()->watching()->limit(3)->pluck('title');
                $body = 'still on your list: '.$titles->join(', ').'. open Najik to see what is close this week.';

                if ($push->toUser($user, 'your week on Najik', $body, ['type' => 'weekly_summary'])) {
                    $sent++;
                }
            }
        });

    $this->info("Sent {$sent} weekly summaries.");
})->purpose('Send a weekly summary to users who stopped opening nudges');
