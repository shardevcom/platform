<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify which authentication guard Passport will use when
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'web',

    'middleware' => [],

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models will utilize your application's default
    | database connection. If you wish to use a different connection you
    | may specify the configured name of the database connection here.
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Token Lifetimes
    |--------------------------------------------------------------------------
    |
    | You may configure the access token and refresh token lifetimes here.
    | Passport's default is 1 year for access tokens, which is too long for
    | a security-conscious API. We default to 1 hour for access tokens and
    | 7 days for refresh tokens. Configure via env vars in production.
    |
    */

    'personal_access_token_expires_in' => env('PASSPORT_PERSONAL_ACCESS_TOKEN_EXPIRES_IN', '1 year'),

    'access_token_expires_in' => env('PASSPORT_ACCESS_TOKEN_EXPIRES_IN', '1 hour'),

    'refresh_token_expires_in' => env('PASSPORT_REFRESH_TOKEN_EXPIRES_IN', '7 days'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token Rotation
    |--------------------------------------------------------------------------
    |
    | When enabled, each time a refresh token is used to obtain a new access
    | token, the used refresh token is revoked and a new one is issued.
    | This prevents replay attacks and limits the window of exposure if a
    | refresh token is compromised. T-CASA-007.
    |
    */

    'refresh_token_rotation' => env('PASSPORT_REFRESH_TOKEN_ROTATION', true),

    /*
    |--------------------------------------------------------------------------
    | Token Reuse
    |--------------------------------------------------------------------------
    |
    | When rotation is enabled, you can allow a small grace window for the
    | same refresh token to be used multiple times (e.g., due to network
    | retries). This is a security trade-off.
    |
    */

    'refresh_token_grace_period' => env('PASSPORT_REFRESH_TOKEN_GRACE_PERIOD', 0),

    /*
    |--------------------------------------------------------------------------
    | Device Tracking for Step-Up MFA
    |--------------------------------------------------------------------------
    |
    | When enabled, the system tracks devices used for authentication and
    | can trigger step-up MFA when a new device is detected. T-CASA-011.
    |
    */

    'device_tracking' => [
        'enabled' => env('PASSPORT_DEVICE_TRACKING', true),
        'max_devices_per_user' => env('PASSPORT_MAX_DEVICES_PER_USER', 10),
        'trust_duration' => env('PASSPORT_DEVICE_TRUST_DURATION', '30 days'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Step-Up MFA
    |--------------------------------------------------------------------------
    |
    | When a new device is detected, require additional MFA verification.
    | T-CASA-011.
    |
    */

    'step_up_mfa' => [
        'enabled' => env('PASSPORT_STEP_UP_MFA', true),
        'required_abilities' => ['mfa'],
        'challenge_methods' => ['totp', 'recovery_code'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Personal Access Token Parity
    |--------------------------------------------------------------------------
    |
    | Ensure Personal Access Tokens and OAuth2 tokens have the same
    | permission model. T-CASA-005.
    |
    */

    'pat_parity' => [
        'enabled' => env('PASSPORT_PAT_PARITY', true),
        'enforce_same_scopes' => true,
    ],

];