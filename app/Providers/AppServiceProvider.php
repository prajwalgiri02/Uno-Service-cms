<?php

namespace App\Providers;

use App\Contracts\PushGateway;
use App\Contracts\SmsGateway;
use App\Services\Push\LogPushGateway;
use App\Services\SettingsService;
use App\Services\Sms\LogSmsGateway;
use App\Support\Phone;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unsupported SMS driver ['.config('services.sms.driver').'].'),
        });

        $this->app->bind(PushGateway::class, fn () => match (config('services.push.driver')) {
            'log' => new LogPushGateway,
            default => throw new InvalidArgumentException('Unsupported push driver ['.config('services.push.driver').'].'),
        });

        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        $phoneKey = fn (Request $request) => (Phone::normalize($request->input('phone')) ?? 'invalid').'|'.$request->ip();

        RateLimiter::for('otp-send', fn (Request $request) => [
            Limit::perMinute(3)->by('otp-send:'.$phoneKey($request)),
            Limit::perHour(30)->by('otp-send-ip:'.$request->ip()),
        ]);

        RateLimiter::for('otp-verify', fn (Request $request) => Limit::perMinute(10)->by('otp-verify:'.$phoneKey($request)));

        RateLimiter::for('token-refresh', fn (Request $request) => Limit::perMinute(20)->by('token-refresh:'.$request->ip()));

        RateLimiter::for('nudge-check', fn (Request $request) => Limit::perMinute(6)->by('nudge-check:'.($request->user('api')?->id ?? $request->ip())));

        RateLimiter::for('check-in', fn (Request $request) => Limit::perMinute(5)->by('check-in:'.($request->user('api')?->id ?? $request->ip())));
    }
}
