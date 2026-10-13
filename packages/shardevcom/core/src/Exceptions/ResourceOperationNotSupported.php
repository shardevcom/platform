<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * The repository explicitly does not support the requested operation
 * (see supportedOperations). Adapters map to 405/400 semantics.
 */
final class ResourceOperationNotSupported extends DomainException
{
    public static function for(string $resource, string $operation): self
    {
        return new self(
            "Operation [{$operation}] is not supported by resource [{$resource}].",
            ['resource' => $resource, 'operation' => $operation]
        );
    }

    public function errorCode(): string
    {
        return 'RESOURCE_OPERATION_NOT_SUPPORTED';
    }
}
