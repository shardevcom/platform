<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Socialite Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth2 providers configuration for social authentication.
    | Google and Microsoft are enabled by default and can be overridden by user config.
    | Additional providers (GitHub, GitLab, etc.) can be added via config.
    |
    */

    'providers' => [
        'google' => [
            'enabled' => env('SOCIALITE_GOOGLE_ENABLED', true),
            'client_id' => env('SOCIALITE_GOOGLE_CLIENT_ID'),
            'client_secret' => env('SOCIALITE_GOOGLE_CLIENT_SECRET'),
            'redirect' => env('SOCIALITE_GOOGLE_REDIRECT', '/api/v1/auth/social/google/callback'),
            'scopes' => ['openid', 'profile', 'email'],
        ],

        'microsoft' => [
            'enabled' => env('SOCIALITE_MICROSOFT_ENABLED', true),
            'client_id' => env('SOCIALITE_MICROSOFT_CLIENT_ID'),
            'client_secret' => env('SOCIALITE_MICROSOFT_CLIENT_SECRET'),
            'redirect' => env('SOCIALITE_MICROSOFT_REDIRECT', '/api/v1/auth/social/microsoft/callback'),
            'scopes' => ['openid', 'profile', 'email'],
        ],
    ],

    'default_redirect' => env('SOCIALITE_DEFAULT_REDIRECT', '/dashboard'),
];