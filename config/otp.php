<?php

return [
    'defaults' => [
        'length' => (int) env('OTP_LENGTH', 4),
        'expires_in_minutes' => 5,
        'max_attempts' => 5,
        'max_sends' => 5,
        'resend_cooldown_seconds' => 60,
        'lockout_minutes' => 30,
    ],

    'purposes' => [
        'login' => [],
    ],

    /*
    | Returns the plain code in the send-OTP response so the mobile app can be
    | tested without an SMS provider. Never enable in production.
    */
    'expose_code' => (bool) env('OTP_EXPOSE_CODE', false),
];
