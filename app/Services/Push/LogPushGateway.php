<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Contracts\PushGateway;
use Illuminate\Support\Facades\Log;

final class LogPushGateway implements PushGateway
{
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool
    {
        Log::info('Push dispatched.', ['token' => substr($deviceToken, 0, 12).'…', 'title' => $title, 'body' => $body, 'data' => $data]);

        return true;
    }
}
