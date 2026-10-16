<?php

declare(strict_types=1);

namespace Shardevcom\Configuration\Providers;

use Shardevcom\Core\Contracts\ConfigurationProvider;
use Shardevcom\Core\Contracts\ConfigurationVisibility;
use Shardevcom\Core\ApplicationContext;

/**
 * Preferences configuration provider (T-081).
 *
 * Demonstrates how a package can register its own configuration.
 */
final class PreferencesConfigurationProvider implements ConfigurationProvider
{
    public function key(): string
    {
        return 'preferences';
    }

    public function visibility(): ConfigurationVisibility
    {
        return ConfigurationVisibility::AUTHENTICATED;
    }

    public function resolve(ApplicationContext $context): array
    {
        return [
            'theme' => 'light',
            'notifications' => [
                'email' => true,
                'push' => false,
            ],
            'language' => $context->locale(),
        ];
    }
}