<?php

declare(strict_types=1);

namespace Shardevcom\Rest\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\DomainException;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\Unauthorized;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * REST exception handler (T-071).
 *
 * Maps domain exceptions to HTTP responses with the standard envelope:
 * {status, code, message, data, meta}
 */
final class RestExceptionHandler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array<class-string<Throwable>>
     */
    protected $dontReport = [
        ResourceNotFound::class,
        ValidationFailed::class,
        ValidationException::class,
        Unauthorized::class,
        Forbidden::class,
        Conflict::class,
        BusinessRuleViolation::class,
        ResourceOperationNotSupported::class,
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->renderable(function (DomainException $e, Request $request) {
            return $this->renderDomainException($e, $request);
        });

        // A FormRequest rejection is the same validation failure the use case
        // would have raised, just one layer earlier. Same envelope, same code.
        $this->renderable(function (ValidationException $e, Request $request) {
            return $this->renderValidationException($e, $request);
        });

        $this->renderable(function (AuthenticationException $e, Request $request) {
            return $this->renderAuthenticationException($e, $request);
        });

        $this->renderable(function (HttpExceptionInterface $e, Request $request) {
            return $this->renderHttpException($e, $request);
        });

        $this->renderable(function (Throwable $e, Request $request) {
            return $this->renderUnexpected($e, $request);
        });
    }

    /**
     * Render a domain exception.
     */
    protected function renderDomainException(DomainException $e, Request $request): JsonResponse
    {
        $apiError = ApiError::fromDomain($e);
        $meta = ApiMeta::anonymous()->with(['details' => $e->context()]);

        $response = ApiResponse::error($apiError, $meta);

        return response()->json($response->toArray(), $apiError->status);
    }

    /**
     * Render a framework validation rejection (422).
     */
    protected function renderValidationException(ValidationException $e, Request $request): JsonResponse
    {
        $apiError = new ApiError(
            status: 422,
            code: 'VALIDATION_FAILED',
            message: 'The given data was invalid.',
            details: $e->errors(),
        );

        $response = ApiResponse::error($apiError, ApiMeta::anonymous());

        return response()->json($response->toArray(), 422);
    }

    /**
     * Render an authentication exception (401).
     */
    protected function renderAuthenticationException(AuthenticationException $e, Request $request): JsonResponse
    {
        $apiError = new ApiError(
            status: 401,
            code: 'UNAUTHORIZED',
            message: $e->getMessage() ?: 'Unauthenticated.',
            details: [],
        );

        $response = ApiResponse::error($apiError, ApiMeta::anonymous());

        return response()->json($response->toArray(), 401);
    }

    /**
     * Render an HTTP exception.
     */
    protected function renderHttpException(HttpExceptionInterface $e): JsonResponse
    {
        $status = $e->getStatusCode();
        $message = $e->getMessage() ?: $this->defaultMessage($status);

        $apiError = new ApiError(
            status: $status,
            code: $this->errorCode($status),
            message: $message,
            details: [],
        );

        $response = ApiResponse::error($apiError, ApiMeta::anonymous());

        return response()->json($response->toArray(), $status);
    }

    /**
     * Render an unexpected exception (500).
     */
    protected function renderUnexpected(Throwable $e, Request $request): JsonResponse
    {
        // Log the full exception
        report($e);

        $status = 500;
        $message = config('app.debug') ? $e->getMessage() : 'Internal server error.';

        $apiError = new ApiError(
            status: $status,
            code: 'INTERNAL_ERROR',
            message: $message,
            details: config('app.debug') ? [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ] : [],
        );

        $response = ApiResponse::error($apiError, ApiMeta::anonymous());

        return response()->json($response->toArray(), $status);
    }

    private function errorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            422 => 'UNPROCESSABLE_ENTITY',
            429 => 'TOO_MANY_REQUESTS',
            500 => 'INTERNAL_ERROR',
            default => 'HTTP_ERROR',
        };
    }

    private function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Unauthenticated.',
            403 => 'Forbidden.',
            404 => 'Resource not found.',
            405 => 'Method not allowed.',
            409 => 'Conflict.',
            422 => 'Unprocessable entity.',
            429 => 'Too many requests.',
            500 => 'Internal server error.',
            default => 'An error occurred.',
        };
    }
}