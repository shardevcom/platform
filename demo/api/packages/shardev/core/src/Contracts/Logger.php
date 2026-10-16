<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Logging contract (T-121).
 *
 * Transport-agnostic, vendor-free. Implementations may wrap Monolog, Symfony Logger,
 * custom writers, etc. The platform only requires these methods for structured
 * logging with correlation IDs.
 */
interface Logger
{
    /**
     * Log an emergency message (system is unusable).
     *
     * @param array<string, mixed> $context additional context (e.g., request_id, correlation_id, user_id)
     */
    public function emergency(string $message, array $context = []): void;

    /**
     * Log an alert message (action must be taken immediately).
     *
     * @param array<string, mixed> $context
     */
    public function alert(string $message, array $context = []): void;

    /**
     * Log a critical message (critical conditions).
     *
     * @param array<string, mixed> $context
     */
    public function critical(string $message, array $context = []): void;

    /**
     * Log an error message (error conditions).
     *
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void;

    /**
     * Log a warning message (warning conditions).
     *
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void;

    /**
     * Log a notice message (normal but significant condition).
     *
     * @param array<string, mixed> $context
     */
    public function notice(string $message, array $context = []): void;

    /**
     * Log an informational message.
     *
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void;

    /**
     * Log a debug message.
     *
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void;

    /**
     * Log with an arbitrary level.
     *
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void;
}