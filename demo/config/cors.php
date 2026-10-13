<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | CORS restrictivo por plataforma (T-CASA-019)
    |--------------------------------------------------------------------------
    |
    | Sin wildcard: solo orígenes explícitos por env. Cada plataforma tiene su
    | prefijo propio (/admin, /employee, /api), así que el matcher de paths
    | debe cubrir todos los prefijos, no solo 'api/*'.
    |
    */

    'paths' => ['api/*', 'admin/*', 'employee/*', 'graphql', 'oauth/*'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => array_values(array_filter([
        env('APP_URL'),
        env('ADMIN_APP_URL'),
        env('EMPLOYEE_APP_URL'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => [
        'Authorization',
        'Content-Type',
        'Accept',
        'X-Request-ID',
        'X-Correlation-ID',
        'X-Timezone',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'Retry-After',
        'API-Version',
        'Link',
    ],

    'max_age' => 3600,

    'supports_credentials' => false,

];