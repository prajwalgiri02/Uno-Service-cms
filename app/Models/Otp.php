<?php

namespace App\Models;

use App\Enums\OtpChannel;
use App\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['channel', 'purpose', 'destination', 'code', 'attempts', 'max_attempts', 'send_count', 'max_sends', 'expires_at'])]
#[Hidden(['code'])]
class Otp extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => OtpChannel::class,
            'purpose' => OtpPurpose::class,
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasExceededAttempts(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }
}
