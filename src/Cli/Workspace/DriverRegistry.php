<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Workspace\Drivers\ApiDriver;
use Shardevcom\Cli\Workspace\Drivers\DesktopDriver;
use Shardevcom\Cli\Workspace\Drivers\MobileDriver;
use Shardevcom\Cli\Workspace\Drivers\WebDriver;

/**
 * Boundary: maps a component type to the driver that knows its toolchain.
 *
 * The single place the CLI decides "who runs npm and who runs gradlew", so
 * every workspace command stays free of type switches.
 */
final class DriverRegistry
{
    public function __construct(private readonly Context $context) {}

    public function driver(ComponentType $type): ComponentDriver
    {
        return match ($type) {
            ComponentType::Api => new ApiDriver($this->context),
            ComponentType::Web => new WebDriver($this->context),
            ComponentType::Mobile => new MobileDriver($this->context),
            ComponentType::Desktop => new DesktopDriver($this->context),
        };
    }
}
