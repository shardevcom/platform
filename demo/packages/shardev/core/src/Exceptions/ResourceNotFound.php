<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * A resource entry does not exist (or is invisible to this context).
 * Adapters map to 404. Never reveals whether the id exists for another
 * tenant — the message stays generic on purpose.
 */
final class ResourceNotFound extends DomainException
{
    public static function for(string $resource, string|int $id): self
    {
        return new self(
            "Resource [{$resource}:{$id}] not found.",
            ['resource' => $resource, 'id' => $id]
        );
    }

    public function errorCode(): string
    {
        return 'RESOURCE_NOT_FOUND';
    }
}
