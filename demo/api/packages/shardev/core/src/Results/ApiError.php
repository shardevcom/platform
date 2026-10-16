<?php

declare(strict_types=1);

namespace Shardevcom\Core\Results;

use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\DomainException;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\RequestEntityTooLarge;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\Unauthorized;
use Shardevcom\Core\Exceptions\UnsupportedMediaType;
use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Transport-neutral failure value (T-014).
 *
 * The domain-to-status table lives HERE exactly once: both the REST
 * adapter (T-071) and the GraphQL adapter (T-074) build from fromDomain(),
 * so the two transports can never diverge on semantics. Adapters only add
 * transport framing (HTTP status line vs GraphQL error extensions).
 */
final class ApiError
{
    /**
     * @param array<string, mixed> $details client-safe details (e.g. field errors)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly array $details = [],
        public readonly int $status = 500,
    ) {
    }

    public static function fromDomain(DomainException $exception): self
    {
        $status = match ($exception::class) {
            Unauthorized::class => 401,
            Forbidden::class => 403,
            ResourceNotFound::class => 404,
            RequestEntityTooLarge::class => 413,
            UnsupportedMediaType::class => 415,
            ValidationFailed::class, BusinessRuleViolation::class => 422,
            Conflict::class => 409,
            ResourceOperationNotSupported::class => 405,
            default => 500,
        };

        $details = $exception instanceof ValidationFailed ? $exception->errors() : [];

        return new self($exception->errorCode(), $exception->getMessage(), $details, $status);
    }

    public static function internal(string $message = 'Internal server error.'): self
    {
        return new self('INTERNAL_ERROR', $message, [], 500);
    }

    /**
     * @return array{code: string, message: string, details: array<string, mixed>, status: int}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'details' => $this->details,
            'status' => $this->status,
        ];
    }
}
