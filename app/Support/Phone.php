<?php

declare(strict_types=1);

namespace App\Support;

final class Phone
{
    public const COUNTRY_CODE = '977';

    /**
     * Nepali mobile numbers: 10 digits starting 97 or 98, optionally prefixed with +977 / 977.
     */
    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * Returns the number in E.164 form (+9779812345678), or null when it is not a Nepali mobile.
     */
    public static function normalize(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        if (str_starts_with($digits, self::COUNTRY_CODE) && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        }

        if (! preg_match('/^9[78]\d{8}$/', $digits)) {
            return null;
        }

        return '+'.self::COUNTRY_CODE.$digits;
    }
}
