<?php

namespace App\Enums;

enum ReportResolution: string
{
    case DISMISSED = 'dismissed';
    case WARNED = 'warned';
    case VENDOR_PAUSED = 'vendor_paused';
    case VENDOR_RESTORED = 'vendor_restored';

    public function label(): string
    {
        return match ($this) {
            self::DISMISSED => 'Dismiss, report not valid',
            self::WARNED => 'Valid, vendor warned',
            self::VENDOR_PAUSED => 'Valid, keep vendor paused',
            self::VENDOR_RESTORED => 'Resolved, restore vendor',
        };
    }
}
