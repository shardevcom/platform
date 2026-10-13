<?php

declare(strict_types=1);

namespace Shardevcom\Configuration\Providers;

use Shardevcom\Core\Contracts\ConfigurationProvider;
use Shardevcom\Core\Contracts\ConfigurationVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Core configuration provider (T-081).
 *
 * Returns locale and timezone settings.
 */
final class CoreConfigurationProvider implements ConfigurationProvider
{
    public function key(): string
    {
        return 'core';
    }

    public function visibility(): ConfigurationVisibility
    {
        return ConfigurationVisibility::PUBLIC;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'locale' => $context->locale(),
            'timezone' => $context->timezone(),
        ];
    }
}