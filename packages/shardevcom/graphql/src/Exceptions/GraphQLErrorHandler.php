<?php

declare(strict_types=1);

namespace Shardevcom\Graphql\Exceptions;

use GraphQL\Error\Error;
use Nuwave\Lighthouse\Execution\ErrorHandler;
use Shardevcom\Core\Exceptions\DomainException;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Results\ApiError;

/**
 * GraphQL error handler (T-074).
 *
 * Maps a domain exception to the GraphQL error spec, reusing the same ApiError
 * the REST adapter serializes so both transports agree on the reason. GraphQL
 * does not get the REST envelope: it gets `errors[].extensions.code`, which is
 * what a GraphQL client expects.
 *
 * Lighthouse's contract is a middleware, not a mapper: __invoke() receives one
 * error plus $next and must either return null to discard the error or return
 * an array shaped like errors[]. Returning null here would silently hide
 * failures, so every error is passed through after being enriched.
 */
final class GraphQLErrorHandler implements ErrorHandler
{
    public function __invoke(?Error $error, \Closure $next): ?array
    {
        $result = $next($error);

        if ($result === null || $error === null) {
            return $result;
        }

        $mapped = $this->map($error);

        if ($mapped === null) {
            return $result;
        }

        $mapped['message'] ??= $result['message'] ?? $error->getMessage();

        return $mapped;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function map(Error $error): ?array
    {
        $previous = $error->getPrevious();

        if (! $previous instanceof DomainException) {
            return null;
        }

        $apiError = ApiError::fromDomain($previous);

        $extensions = ['code' => $apiError->code];

        if ($previous instanceof ValidationFailed) {
            $extensions['details'] = $previous->errors();
        } elseif ($previous instanceof ResourceNotFound) {
            $extensions['details'] = [
                'resource' => $previous->context()['resource'] ?? null,
                'id' => $previous->context()['id'] ?? null,
            ];
        } elseif ($previous instanceof Forbidden) {
            $extensions['details'] = [
                'resource' => $previous->context()['resource'] ?? null,
                'action' => $previous->context()['action'] ?? null,
            ];
        }

        return [
            'message' => $apiError->message,
            'extensions' => $extensions,
        ];
    }
}