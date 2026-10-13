<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Exceptions\Unauthorized;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting por identidad y por operación sensible.
 *
 * Corre PRE-auth: resuelve la identidad de forma oportunista vía el contrato
 * AuthenticationProvider sin fallar la request. Un token ausente o inválido se
 * cuenta como anónimo y la request sigue su curso (el auth real responderá
 * 401). De ahí que el límite de anónimos aplique también a intentos de login.
 *
 * Buckets (config: security.rate_limit):
 * - anónimo:       crono `ip:{ip}`            (default 30 req/min)
 * - autenticado:   crono `user:{userId}`      (default 120 req/min)
 * - operación:     crono `op:{ruta}:user:{uid}:tenant:{tid}` para operaciones
 *   caras configuradas (import/export): por usuario Y por tenant, con límite
 *   más estricto que el bucket base.
 *
 * Store: usa el almacén de cache por defecto a menos que se configure
 * `rate_limit.store`. En despliegues con más de una instancia debe apuntar a
 * un store compartido (p.ej. Redis).
 */
final class RateLimitMiddleware
{
    public function __construct(
        private readonly RateLimiter $limiter,
        private readonly CacheFactory $cache,
        private readonly AuthenticationProvider $auth,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $config = config('security.rate_limit');

        if (($config['enabled'] ?? true) === false) {
            return $next($request);
        }

        $limiter = $this->limiter((string) ($config['store'] ?? ''));
        $userId = $this->resolveUserId($request);
        $tenantId = $request->header('X-Tenant-ID');

        $buckets = [];
        if ($userId === null) {
            $buckets[] = [
                'label' => 'anonymous:'.$request->ip(),
                'key' => 'rlimit:ip:'.$request->ip(),
                'max' => (int) ($config['anonymous']['limit'] ?? 30),
                'decay' => max(1, (int) ($config['anonymous']['decay_seconds'] ?? 60)),
                'primary' => true,
            ];
        } else {
            $buckets[] = [
                'label' => 'user:'.$userId,
                'key' => 'rlimit:user:'.$userId,
                'max' => (int) ($config['authenticated']['limit'] ?? 120),
                'decay' => max(1, (int) ($config['authenticated']['decay_seconds'] ?? 60)),
                'primary' => true,
            ];
        }

        $opKey = $this->operationKey($request);
        if ($opKey !== null && $userId !== null && isset($config['operations'][$opKey])) {
            $op = $config['operations'][$opKey];
            $buckets[] = [
                'label' => 'operation:'.$opKey,
                'key' => sprintf('rlimit:op:%s:user:%s:tenant:%s', $opKey, $userId, $tenantId ?? 'none'),
                'max' => (int) ($op['limit'] ?? 1),
                'decay' => max(1, (int) ($op['decay_seconds'] ?? 3600)),
                'primary' => false,
            ];
        }

        $primary = null;
        $exceeded = null;
        foreach ($buckets as $bucket) {
            // tooManyAttempts trips at >= max, so check BEFORE hitting: the
            // configured limit is fully allowed and the very next request is
            // the one throttled (request 31 for an anonymous limit of 30).
            if ($limiter->tooManyAttempts($bucket['key'], $bucket['max'])) {
                $exceeded = $bucket;
                $exceeded['limiter'] = $limiter;
                break;
            }
            $limiter->hit($bucket['key'], $bucket['decay']);
            if ($bucket['primary']) {
                $primary = compact('limiter') + $bucket;
            }
        }

        if ($exceeded !== null) {
            $retryAfter = $exceeded['limiter']->availableIn($exceeded['key']);

            return response()->json([
                'status' => 'error',
                'code' => 'RATE_LIMITED',
                'message' => 'Too Many Requests.',
                'data' => null,
                'meta' => [
                    'details' => [
                        'scope' => $exceeded['label'],
                        'limit' => $exceeded['max'],
                        'retry_after' => $retryAfter,
                    ],
                ],
            ], 429, [
                'X-RateLimit-Limit' => (string) $exceeded['max'],
                'X-RateLimit-Remaining' => '0',
                'Retry-After' => (string) $retryAfter,
                'X-RateLimit-Reset' => (string) (time() + $retryAfter),
            ]);
        }

        $response = $next($request);

        if ($primary !== null) {
            $attempts = $primary['limiter']->attempts($primary['key']);
            $response->headers->set('X-RateLimit-Limit', (string) $primary['max']);
            $response->headers->set('X-RateLimit-Remaining', (string) max(0, $primary['max'] - $attempts));
        }

        return $response;
    }

    private function limiter(string $store): RateLimiter
    {
        if ($store === '') {
            return $this->limiter;
        }

        return new RateLimiter($this->cache->store($store));
    }

    /**
     * Operation bucket key for a request, if any.
     *
     * REST: route name (products.import, products.export...). The /graphql
     * route gets one single key for mutations (`graphql_mutation`) so every
     * write is bounded tighter than reads; queries ride the base buckets.
     */
    private function operationKey(Request $request): ?string
    {
        $routeName = $request->route()?->getName();

        if ($routeName === 'graphql' && $this->isGraphqlMutation($request)) {
            return 'graphql_mutation';
        }

        return $routeName;
    }

    private function isGraphqlMutation(Request $request): bool
    {
        $query = $request->input('query');

        return is_string($query) && str_contains(ltrim($query), 'mutation');
    }

    private function resolveUserId(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null || ! str_starts_with(strtolower(trim($header)), 'bearer ')) {
            return null;
        }

        try {
            return $this->auth->identityFromToken(trim(substr($header, 7)))->id;
        } catch (Unauthorized) {
            return null;
        }
    }
}