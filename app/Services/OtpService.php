<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Exceptions\OtpException;
use App\Models\Otp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class OtpService
{
    /**
     * Creates or replaces the code for a destination, enforcing the resend
     * cooldown and the send limit per lockout window.
     *
     * @return array{otp: Otp, code: string}
     *
     * @throws OtpException
     */
    public function issue(OtpChannel $channel, OtpPurpose $purpose, string $destination): array
    {
        $settings = $this->settings($purpose);
        $code = $this->generateCode($settings['length']);

        $otp = DB::transaction(function () use ($channel, $purpose, $destination, $settings, $code): Otp {
            $otp = Otp::query()
                ->where('channel', $channel->value)
                ->where('purpose', $purpose->value)
                ->where('destination', $destination)
                ->lockForUpdate()
                ->first();

            $attributes = [
                'code' => Hash::make($code),
                'attempts' => 0,
                'max_attempts' => $settings['max_attempts'],
                'max_sends' => $settings['max_sends'],
                'expires_at' => now()->addMinutes($settings['expires_in_minutes']),
            ];

            if (! $otp) {
                return Otp::query()->create([
                    'channel' => $channel->value,
                    'purpose' => $purpose->value,
                    'destination' => $destination,
                    'send_count' => 1,
                    ...$attributes,
                ]);
            }

            $windowEndsAt = $otp->updated_at->copy()->addMinutes($settings['lockout_minutes']);
            $windowOpen = $windowEndsAt->isFuture();

            if ($windowOpen && $otp->send_count >= $otp->max_sends) {
                throw new OtpException(
                    'Too many codes requested. Please try again later.',
                    'phone',
                    (int) ceil(now()->diffInSeconds($windowEndsAt)),
                );
            }

            $availableAt = $otp->updated_at->copy()->addSeconds($settings['resend_cooldown_seconds']);

            if ($availableAt->isFuture()) {
                $retryAfter = (int) ceil(now()->diffInSeconds($availableAt));

                throw new OtpException("Please wait {$retryAfter} seconds before requesting another code.", 'phone', $retryAfter);
            }

            $otp->update([...$attributes, 'send_count' => $windowOpen ? $otp->send_count + 1 : 1]);

            return $otp->refresh();
        });

        return ['otp' => $otp, 'code' => $code];
    }

    /**
     * Checks the code and deletes it on success so it cannot be replayed.
     *
     * @throws OtpException
     */
    public function verify(OtpChannel $channel, OtpPurpose $purpose, string $destination, string $code): void
    {
        $result = DB::transaction(function () use ($channel, $purpose, $destination, $code): array {
            $otp = Otp::query()
                ->where('channel', $channel->value)
                ->where('purpose', $purpose->value)
                ->where('destination', $destination)
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return ['status' => 'not_found'];
            }

            if ($otp->isExpired()) {
                return ['status' => 'expired'];
            }

            if ($otp->hasExceededAttempts()) {
                return ['status' => 'attempt_limit'];
            }

            if (! Hash::check($code, $otp->code)) {
                $otp->increment('attempts');

                return ['status' => 'invalid', 'remaining' => max(0, $otp->max_attempts - $otp->attempts)];
            }

            $otp->delete();

            return ['status' => 'verified'];
        });

        match ($result['status']) {
            'verified' => null,
            'not_found' => throw new OtpException('Request a new code first.'),
            'expired' => throw new OtpException('This code has expired. Request a new one.'),
            'attempt_limit' => throw new OtpException('Too many incorrect attempts. Request a new code.'),
            'invalid' => throw new OtpException("That code is incorrect. {$result['remaining']} attempts remaining."),
        };
    }

    public function expiresInSeconds(Otp $otp): int
    {
        return (int) max(0, ceil(now()->diffInSeconds($otp->expires_at, false)));
    }

    public function resendInSeconds(Otp $otp): int
    {
        $availableAt = $otp->updated_at->copy()->addSeconds($this->settings($otp->purpose)['resend_cooldown_seconds']);

        return (int) max(0, ceil(now()->diffInSeconds($availableAt, false)));
    }

    public function clearExpired(): int
    {
        return Otp::query()
            ->where('expires_at', '<=', now()->subMinutes((int) config('otp.defaults.lockout_minutes')))
            ->delete();
    }

    /**
     * @return array{length: int, expires_in_minutes: int, max_attempts: int, max_sends: int, resend_cooldown_seconds: int, lockout_minutes: int}
     */
    private function settings(OtpPurpose $purpose): array
    {
        return array_merge(config('otp.defaults'), config("otp.purposes.{$purpose->value}", []));
    }

    private function generateCode(int $length): string
    {
        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }
}
