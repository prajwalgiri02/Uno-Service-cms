<?php

namespace App\Enums;

enum CheckInMethod: string
{
    case SHOP_CODE = 'shop_code';
    case CUSTOMER_CODE = 'customer_code';
}
