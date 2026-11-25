<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SmsGateway;
use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use App\Enums\UserRole;
use App\Exceptions\OtpException;
use App\Models\Otp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class PhoneLoginService
{
    public function __construct(
        private readonly OtpService $otpService,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * @return array{otp: Otp, code: string}
     *
     * @throws OtpException
     */
    public function sendCode(string $phone): array
    {
        $issued = $this->otpService->issue(OtpChannel::SMS, OtpPurpose::LOGIN, $phone);

        $minutes = (int) ceil($this->otpService->expiresInSeconds($issued['otp']) / 60);

        $this->sms->send($phone, "{$issued['code']} is your Najik code. It expires in {$minutes} minutes.");

        return $issued;
    }

    /**
     * Verifies the code, then finds or creates the account for the phone.
     * Every app user can browse as a customer; signing in from the shop flow adds the vendor role.
     *
     * @return array{user: User, is_new_user: bool}
     *
     * @throws OtpException
     */
    public function verify(string $phone, string $code, UserRole $as): array
    {
        $this->otpService->verify(OtpChannel::SMS, OtpPurpose::LOGIN, $phone, $code);

        return DB::transaction(function () use ($phone, $as): array {
            $user = User::query()->where('phone', $phone)->lockForUpdate()->first();
            $isNew = $user === null;

            $user ??= User::query()->create(['phone' => $phone]);

            $roles = [UserRole::CUSTOMER->value];

            if ($as === UserRole::VENDOR) {
                $roles[] = UserRole::VENDOR->value;
            }

            foreach ($roles as $role) {
                if (! $user->hasRole($role)) {
                    $user->assignRole($role);
                }
            }

            $user->forceFill([
                'phone_verified_at' => $user->phone_verified_at ?? now(),
                'last_login_at' => now(),
            ])->save();

            return ['user' => $user, 'is_new_user' => $isNew];
        });
    }
}
