<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Http;

use Closure;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Illuminate\Http\Request;
use Shardevcom\Core\ApplicationContext;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * T-CASA-023 correlation middleware — the request-side half of every line on
 * the wire.
 *
 * - Accepts an inbound correlation id ONLY when it is a valid UUIDv4
 *   (CorrelationIds::accept) and generates its own otherwise.
 * - request_id is always a fresh server-side id: two concurrent requests can
 *   never share one, even when a client tries to force X-Request-ID.
 * - Wraps $next in a try/catch so a middle pipeline failure (auth 401, rate
 *   429) still emits the terminal ERROR line with the same correlation id.
 *
 * The terminal line carries the platform dimensions; user/tenant ids are read
 * from the ApplicationContext that transport builders (T-091) attach to the
 * request, so the middleware never re-authenticates.
 */
final class CorrelationMiddleware
{
    public function __construct(private readonly \Shardevcom\Core\Contracts\Logger $logger)
    {
    }

    public function handle(Request $request, Closure $next): mixed
    {
        $correlationId = $this->incomingCorrelationId($request) ?? \Shardevcom\Observability\Support\CorrelationIds::generate();
        $requestId = \Shardevcom\Observability\Support\CorrelationIds::generate();
        $startedAt = hrtime(true);

        $request->attributes->set(HttpApplicationContextBuilder::CORRELATION_ATTRIBUTE, $correlationId);
        $request->attributes->set(HttpApplicationContextBuilder::REQUEST_ATTRIBUTE, $requestId);
        $request->attributes->set(HttpApplicationContextBuilder::GUARD_ATTRIBUTE, $this->guessGuard($request));

        try {
            $response = $next($request);
        } catch (Throwable $failure) {
            $this->logTerminal($request, $correlationId, $startedAt, 500, 'error');

            throw $failure;
        }

        $status = $response->getStatusCode();
        $level = $status >= 500 ? 'error' : 'info';

        $this->logTerminal($request, $correlationId, $startedAt, $status, $level);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensions(Request $request, string $correlationId, int $startedAt, int $status): array
    {
        /** @var ApplicationContext|null $context */
        $context = $request->attributes->get(HttpApplicationContextBuilder::CONTEXT_ATTRIBUTE);

        $dimensions = [
            'correlation_id' => $correlationId,
            'request_id' => $request->attributes->get(HttpApplicationContextBuilder::REQUEST_ATTRIBUTE),
            'user_id' => $context?->userId(),
            'tenant_id' => $context?->tenantId(),
            'guard' => $context?->guard(),
            'ip' => $request->ip(),
            'ua' => $request->userAgent(),
            'status' => $status,
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 3),
            'method' => $request->method(),
            'path' => $request->path(),
        ];

        return $this->withoutNulls($dimensions);
    }

    private function logTerminal(Request $request, string $correlationId, int $startedAt, int $status, string $level): void
    {
        $message = $status >= 400
            ? 'HTTP request failed'
            : 'HTTP request completed';

        $this->logger->log($level, $message, $this->dimensions($request, $correlationId, $startedAt, $status));
    }

    private function incomingCorrelationId(Request $request): ?string
    {
        $inbound = $request->header('X-Correlation-ID') ?: $request->attributes->get(HttpApplicationContextBuilder::CORRELATION_ATTRIBUTE);

        if (is_string($inbound) && $inbound !== '') {
            return \Shardevcom\Observability\Support\CorrelationIds::accept($inbound);
        }

        return null;
    }

    /**
     * The platform guard this HTTP request belongs to. Mirrors T-204's
     * prefix→platform resolution: the {guard} route parameter is not present
     * on generated platform routes, so it is derived from the routing prefix
     * against config('platforms.platforms'). Used as the guard dimension when
     * the context carried no guard.
     */
    private function guessGuard(Request $request): ?string
    {
        $path = '/'.trim($request->path(), '/');
        $fallback = null;

        foreach ((array) config('platforms.platforms', []) as $key => $platform) {
            $prefix = is_array($platform) ? (string) ($platform['prefix'] ?? '') : '';

            if ($prefix === '') {
                $fallback = $key;

                continue;
            }

            if (str_starts_with($path, '/'.trim($prefix, '/').'/')) {
                return $key;
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    private function withoutNulls(array $fields): array
    {
        return array_filter($fields, static fn ($value): bool => $value !== null && $value !== '');
    }
}