<?php

declare(strict_types=1);

/*
 * Shardev platform configuration (T-002).
 *
 * Only platform-specific keys live here. Everything Laravel already
 * provides (app, auth, database, ...) stays in its own config file.
 */

return [

    'version' => env('PLATFORM_VERSION', '1.0'),

    'api' => [
        'rest' => (bool) env('API_REST_ENABLED', true),
        'graphql' => (bool) env('API_GRAPHQL_ENABLED', true),
    ],

    'packages' => [
        'dir' => env('PLATFORM_PACKAGES_DIR', 'packages'),
        'vendor' => env('PLATFORM_VENDOR_NAMESPACE', 'shardev'),
    ],

    'import' => [
        'max_file_size' => (int) env('PLATFORM_IMPORT_MAX_FILE_SIZE', 10 * 1024 * 1024),
        'max_rows' => (int) env('PLATFORM_IMPORT_MAX_ROWS', 10_000),
        'retention' => [
            'completed' => 90,
            'failed' => 30,
            'stuck_hours' => 24,
        ],
    ],

];
