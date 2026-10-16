<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default API Version
    |--------------------------------------------------------------------------
    |
    | The default API version used when no version is specified in the request.
    | Format: '1.0', 'v1', '2024-01', etc.
    |
    */

    'version' => [
        'default' => env('REST_API_VERSION', '1.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Deprecated Endpoints
    |--------------------------------------------------------------------------
    |
    | List of deprecated endpoints with their deprecation info.
    | Each entry can have:
    |   - link: URL to migration guide or new endpoint
    |   | since: Version when deprecated
    |   | message: Optional custom message
    |
    */

    'deprecated' => [
        // Example:
        // 'api/v1/users/{id}' => [
        //     'link' => 'https://docs.example.com/migration/v2-users',
        //     'since' => '2.0',
        //     'message' => 'Use /api/v2/users/{id} instead',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sunset Dates
    |--------------------------------------------------------------------------
    |
    | Endpoints that will be removed (sunset). Format: 'path' => 'Sat, 01 Jan 2025 00:00:00 GMT'
    | The Sunset header will be added to responses for these endpoints.
    |
    */

    'sunset' => [
        // Example:
        // 'api/v1/legacy/endpoint' => 'Sat, 01 Jan 2025 00:00:00 GMT',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Documentation URL
    |--------------------------------------------------------------------------
    |
    | URL for API documentation. Used for Link header with rel="describedby".
    |
    */

    'docs_url' => env('REST_DOCS_URL', '/docs/api'),

    /*
    |--------------------------------------------------------------------------
    | Default API Version
    |--------------------------------------------------------------------------
    |
    | The default version when none is specified in the request.
    |
    */

    'version' => [
        'default' => env('REST_API_VERSION', '1.0'),
    ],
];