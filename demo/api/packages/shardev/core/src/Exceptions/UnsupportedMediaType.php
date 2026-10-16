<?php

declare(strict_types=1);

namespace Shardevcom\Core\Exceptions;

/**
 * The file does not have the content it claims (wrong declared format,
 * renamed archive, binary garbage). Decided by real content inspection,
 * never by filename. Adapters map to 415.
 */
final class UnsupportedMediaType extends DomainException
{
    public function errorCode(): string
    {
        return 'UNSUPPORTED_MEDIA_TYPE';
    }
}