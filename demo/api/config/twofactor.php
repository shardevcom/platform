<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | TOTP (Time-based One-Time Password) configuration per RFC 6238.
    |
    */

    'enabled' => env('TWOFACTOR_ENABLED', true),

    'issuer' => env('TWOFACTOR_ISSUER', 'Shardev Platform'),

    'window' => env('TWOFACTOR_WINDOW', 1), // Number of 30-second windows to allow (1 = 30 seconds before/after)

    'recovery_codes_count' => env('TWOFACTOR_RECOVERY_CODES_COUNT', 8),

    'recovery_codes_length' => env('TWOFACTOR_RECOVERY_CODES_LENGTH', 10),

    'digits' => env('TWOFACTOR_DIGITS', 6),

    'algorithm' => env('TWOFACTOR_ALGORITHM', 'sha1'), // sha1, sha256, sha512

    'period' => env('TWOFACTOR_PERIOD', 30), // seconds per code

    'qr_code' => [
        'backend' => env('TWOFACTOR_QR_BACKEND', 'gd'), // gd, imagick
    ],
];