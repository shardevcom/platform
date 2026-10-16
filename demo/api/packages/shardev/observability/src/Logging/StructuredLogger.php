<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Logging;

use Psr\Log\LoggerInterface;
use Shardevcom\Core\Contracts\Logger;

/**
 * Real Logger adapter (T-CASA-023 / T-121): routes the platform contract onto
 * any PSR-3 logger the project already runs (Monolog through Laravel's Log
 * manager, or a direct stream in tests).
 *
 * Nothing here knows HTTP or Laravel: middleware and lifecycle hooks pass
 * correlation/request ids and dimensions through $context, which the
 * configured formatter (JsonFormatter) renders.
 */
final class StructuredLogger implements Logger
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }
}