<?php

namespace App\Enums;

enum ShopStatus: string
{
    case PENDING = 'pending';
    case CHANGES_REQUESTED = 'changes_requested';
    case APPROVED = 'approved';
    case PAUSED = 'paused';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::CHANGES_REQUESTED => 'Changes asked',
            self::APPROVED => 'Approved',
            self::PAUSED => 'Paused',
            self::REJECTED => 'Rejected',
        };
    }
}
