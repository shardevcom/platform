<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Widgets;

use Shardevcom\Widgets\Contracts\WidgetProvider;
use Shardevcom\Widgets\Contracts\WidgetVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Metric widget provider.
 *
 * Returns key metric values for dashboards.
 */
final class MetricWidgetProvider implements WidgetProvider
{
    public function key(): string
    {
        return 'metric';
    }

    public function visibility(): WidgetVisibility
    {
        return WidgetVisibility::AUTHENTICATED;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'type' => 'metric',
            'metrics' => [
                [
                    'key' => 'total_users',
                    'label' => 'Total Users',
                    'value' => 1234,
                    'change' => '+12%',
                    'trend' => 'up',
                ],
                [
                    'key' => 'active_sessions',
                    'label' => 'Active Sessions',
                    'value' => 56,
                    'change' => '-3%',
                    'trend' => 'down',
                ],
                [
                    'key' => 'revenue',
                    'label' => 'Revenue',
                    'value' => '$45,678',
                    'change' => '+8%',
                    'trend' => 'up',
                ],
                [
                    'key' => 'conversion_rate',
                    'label' => 'Conversion Rate',
                    'value' => '3.2%',
                    'change' => '+0.5%',
                    'trend' => 'up',
                ],
            ],
        ];
    }
}