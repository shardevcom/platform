<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * Base of every domain/application failure (T-013).
 *
 * Transport-neutral by rule: it carries no HTTP status and never touches
 * framework HTTP exception classes or abort helpers.
 * Each failure carries a stable errorCode() shared by the REST and GraphQL
 * adapters, plus a safe context array for server-side logging. The context
 * must NEVER contain raw SQL, query bindings, stack traces or secrets —
 * adapters only expose message + code to clients.
 */
abstract class DomainException extends \RuntimeException
{
    /**
     * @param array<string, mixed> $context safe, loggable, never leaked raw to clients
     */
    public function __construct(string $message = '', array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->context = $context;
    }

    abstract public function errorCode(): string;

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }

    /** @var array<string, mixed> */
    private array $context;
}
