<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Widgets Configuration
    |--------------------------------------------------------------------------
    |
    | Default widget providers and their visibility.
    |
    */

    'providers' => [
        'card' => [
            'class' => \Shardevcom\Widgets\Widgets\CardWidgetProvider::class,
            'visibility' => 'public',
        ],
        'chart' => [
            'class' => \Shardevcom\Widgets\Widgets\ChartWidgetProvider::class,
            'visibility' => 'authenticated',
        ],
        'metric' => [
            'class' => \Shardevcom\Widgets\Widgets\MetricWidgetProvider::class,
            'visibility' => 'authenticated',
        ],
        'table' => [
            'class' => \Shardevcom\Widgets\Widgets\TableWidgetProvider::class,
            'visibility' => 'admin',
        ],
    ],

    'default_visibility' => 'authenticated',
];