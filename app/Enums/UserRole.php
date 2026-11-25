<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case CUSTOMER = 'customer';
    case VENDOR = 'vendor';

    /** @return list<string> */
    public static function appRoles(): array
    {
        return [self::CUSTOMER->value, self::VENDOR->value];
    }
}
