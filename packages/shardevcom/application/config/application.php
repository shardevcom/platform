<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Application Context Defaults
    |--------------------------------------------------------------------------
    |
    | Default values used when building ApplicationContext. These can be
    | overridden per-request via headers, CLI options, job payload, etc.
    |
    */

    'context' => [
        'defaults' => [
            'locale' => env('APP_LOCALE', 'en'),
            'timezone' => env('APP_TIMEZONE', 'UTC'),
            'application_id' => env('APP_ID', null),
            'tenant_id' => env('APP_TENANT_ID', null),
            'device_id' => null,
            'request_id' => null,
            'correlation_id' => null,
        ],

        /*
        |--------------------------------------------------------------------------
        | Header Names
        |--------------------------------------------------------------------------
        |
        | HTTP header names used to extract context information.
        | Can be customized if needed.
        |
        */

        'headers' => [
            'tenant' => 'X-Tenant-ID',
            'application' => 'X-Application-ID',
            'device' => 'X-Device-ID',
            'request_id' => 'X-Request-ID',
            'correlation_id' => 'X-Correlation-ID',
            'timezone' => 'X-Timezone',
        ],
    ],
];