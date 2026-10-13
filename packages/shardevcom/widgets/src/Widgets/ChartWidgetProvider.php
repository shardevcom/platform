<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Widgets;

use Shardevcom\Widgets\Contracts\WidgetProvider;
use Shardevcom\Widgets\Contracts\WidgetVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Chart widget provider.
 *
 * Returns chart configuration data for dashboards.
 */
final class ChartWidgetProvider implements WidgetProvider
{
    public function key(): string
    {
        return 'chart';
    }

    public function visibility(): WidgetVisibility
    {
        return WidgetVisibility::AUTHENTICATED;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'type' => 'chart',
            'chartType' => 'line',
            'title' => 'Activity Overview',
            'data' => [
                'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                'datasets' => [
                    [
                        'label' => 'Users',
                        'data' => [12, 19, 3, 5, 2, 3, 7],
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    ],
                    [
                        'label' => 'Sessions',
                        'data' => [8, 12, 2, 8, 1, 5, 4],
                        'borderColor' => 'rgb(16, 185, 129)',
                        'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'interaction' => ['mode' => 'index', 'intersect' => false],
            ],
        ];
    }
}