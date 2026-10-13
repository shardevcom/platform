<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * Input failed validation at the single validation point (DTO).
 * Carries field errors for clients. Adapters map to 422.
 */
final class ValidationFailed extends DomainException
{
    /**
     * @param array<string, mixed> $errors field keyed failures
     * @param array<string, mixed> $context
     */
    public function __construct(array $errors, string $message = 'Validation failed.', array $context = [])
    {
        parent::__construct($message, $context);
        $this->errors = $errors;
    }

    public function errorCode(): string
    {
        return 'VALIDATION_FAILED';
    }

    /**
     * @return array<string, mixed>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @var array<string, mixed> */
    private array $errors;
}
