<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Widgets;

use Shardevcom\Widgets\Contracts\WidgetProvider;
use Shardevcom\Widgets\Contracts\WidgetVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Card widget provider.
 *
 * Returns card-style widget data for dashboards.
 */
final class CardWidgetProvider implements WidgetProvider
{
    public function key(): string
    {
        return 'card';
    }

    public function visibility(): WidgetVisibility
    {
        return WidgetVisibility::PUBLIC;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'type' => 'card',
            'title' => 'Welcome Card',
            'description' => 'Welcome to your dashboard',
            'actions' => [
                ['label' => 'Get Started', 'url' => '/getting-started'],
                ['label' => 'Documentation', 'url' => '/docs'],
            ],
            'meta' => [
                'version' => '1.0',
                'lastUpdated' => date('c'),
            ],
        ];
    }
}