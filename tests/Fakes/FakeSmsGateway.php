<?php

namespace Tests\Fakes;

use App\Contracts\SmsGateway;

class FakeSmsGateway implements SmsGateway
{
    /** @var list<array{to: string, message: string}> */
    public array $sent = [];

    public function send(string $to, string $message): void
    {
        $this->sent[] = ['to' => $to, 'message' => $message];
    }

    public function lastCodeFor(string $to): ?string
    {
        foreach (array_reverse($this->sent) as $sms) {
            if ($sms['to'] === $to && preg_match('/^(\d+) /', $sms['message'], $m)) {
                return $m[1];
            }
        }

        return null;
    }
}
