<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\PushGateway;
use App\Models\User;

class PushService
{
    public function __construct(
        private readonly PushGateway $gateway,
    ) {}

    /**
     * @param  array<string, string>  $data
     * @return bool true when at least one device accepted the message
     */
    public function toUser(User $user, string $title, string $body, array $data = []): bool
    {
        $delivered = false;

        foreach ($user->deviceTokens()->where('notifications_allowed', true)->get() as $device) {
            $delivered = $this->gateway->send($device->token, $title, $body, $data) || $delivered;
        }

        return $delivered;
    }
}
