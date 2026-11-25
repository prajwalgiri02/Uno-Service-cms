<?php

declare(strict_types=1);

namespace App\Support;

final class Week
{
    /**
     * Nepal works Sunday to Friday; ISO weekday numbers (1 = Monday … 7 = Sunday).
     */
    public const WORKDAYS = [1, 2, 3, 4, 5, 7];

    /**
     * @param  list<int>  $days
     */
    public static function isWorkdays(array $days): bool
    {
        sort($days);

        return $days === self::WORKDAYS;
    }
}
