<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Contracts;

use Shardevcom\Core\ApplicationContext;

/**
 * Contract for a widget provider.
 *
 * Each widget provider returns data for a specific widget type (card, chart, metric, etc.).
 */
interface WidgetProvider
{
    public function key(): string;

    public function visibility(): WidgetVisibility;

    public function resolve(ApplicationContext $context): mixed;
}