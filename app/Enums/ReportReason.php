<?php

namespace App\Enums;

enum ReportReason: string
{
    case CLOSED_WHEN_OPEN = 'closed_when_open';
    case PRICE_HIGHER = 'price_higher';
    case WAITED_LONG = 'waited_long';
    case RUDE_OR_UNSAFE = 'rude_or_unsafe';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CLOSED_WHEN_OPEN => 'Closed when open listed',
            self::PRICE_HIGHER => 'Price was way higher',
            self::WAITED_LONG => 'Said no wait, waited long',
            self::RUDE_OR_UNSAFE => 'Rude or unsafe',
            self::OTHER => 'Something else',
        };
    }

    public function emoji(): string
    {
        return match ($this) {
            self::CLOSED_WHEN_OPEN => '🚪',
            self::PRICE_HIGHER => '💸',
            self::WAITED_LONG => '⏳',
            self::RUDE_OR_UNSAFE => '😕',
            self::OTHER => '✏️',
        };
    }
}
