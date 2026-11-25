<?php

declare(strict_types=1);

namespace App\Contracts;

interface PushGateway
{
    /**
     * @param  array<string, string>  $data
     * @return bool whether the provider accepted the message
     */
    public function send(string $deviceToken, string $title, string $body, array $data = []): bool;
}
