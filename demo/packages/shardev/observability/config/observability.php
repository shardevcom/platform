<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Observability (T-CASA-023)
    |--------------------------------------------------------------------------
    |
    | Which headers the correlation middleware honours, which log level it uses
    | for the terminal request line, and (future) metric/trace sampling. With
    | no collectors installed the Meter/Tracer contracts bind to the Null
    | adapters; wiring a Prometheus or OTLP endpoint later is a provider edit,
    | not a contract change.
    |
    */

    'correlation' => [
        // Inbound header name for the correlation/trace id.
        'inbound_header' => 'X-Correlation-ID',
        // Only UUIDv4 values are accepted; anything else is regenerated.
        'require_uuidv4' => true,
    ],

    'log' => [
        // Default channel to use in Laravel's logging.php.
        'channel' => 'structured',
    ],
];