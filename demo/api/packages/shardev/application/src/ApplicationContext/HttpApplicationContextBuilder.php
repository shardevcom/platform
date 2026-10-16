<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Illuminate\Http\Request;
use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\PermissionResolver;
use Shardevcom\Core\Exceptions\Unauthorized;

/**
 * HTTP-specific ApplicationContext builder.
 *
 * Extracts Bearer token from Authorization header, authenticates via
 * AuthenticationProvider, and builds context with permissions and
 * tenant/device from request headers.
 */
final class HttpApplicationContextBuilder implements ApplicationContextBuilder
{
    /**
     * Request attribute key where the transport builder stores the context it
     * produced, so downstream/terminal consumers (e.g. the T-CASA-023
     * correlation middleware) can read dimensions WITHOUT re-authenticating.
     */
    public const CONTEXT_ATTRIBUTE = 'shardev.context';

    /**
     * Attribute keys the correlation middleware (T-CASA-023) writes BEFORE the
     * guarded pipeline runs. The builder honors them FIRST so the context the
     * lifecycle logs carries the SAME ids the middleware assigned — honoring a
     * raw header here would reintroduce unvalidated client strings into logs.
     */
    public const CORRELATION_ATTRIBUTE = 'shardev.correlation_id';

    public const REQUEST_ATTRIBUTE = 'shardev.request_id';

    public const GUARD_ATTRIBUTE = 'shardev.guard';

    public function __construct(
        private readonly AuthenticationProvider $auth,
        private readonly BaseApplicationContextBuilder $base,
    ) {}

    public function fromIdentity(
        ApplicationIdentity $identity,
        array $additional = []
    ): ApplicationContext {
        return $this->base->fromIdentity($identity, $additional);
    }

    public function forGuest(array $additional = []): ApplicationContext
    {
        return $this->base->forGuest($additional);
    }

    /**
     * Build context from an HTTP request.
     *
     * Extracts Bearer token, authenticates, and enriches with
     * request-specific data (locale, timezone, tenant, correlation ID).
     *
     * @throws Unauthorized if token is present but invalid
     */
    public function fromRequest(Request $request): ApplicationContext
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->attach($request, $this->buildGuestContext($request));
        }

        $identity = $this->auth->identityFromToken($token);

        return $this->attach($request, $this->buildAuthenticatedContext($identity, $request));
    }

    /**
     * Try to build context from request, returning null if no auth.
     *
     * Unlike fromRequest(), this does not throw on invalid token -
     * it treats invalid token as guest. Use for optional authentication.
     */
    public function tryFromRequest(Request $request): ?ApplicationContext
    {
        $token = $this->extractBearerToken($request);

        if ($token === null) {
            return $this->attach($request, $this->buildGuestContext($request));
        }

        try {
            $identity = $this->auth->identityFromToken($token);
        } catch (Unauthorized) {
            return $this->attach($request, $this->buildGuestContext($request));
        }

        return $this->attach($request, $this->buildAuthenticatedContext($identity, $request));
    }

    /**
     * Make the context observable downstream: same request object, attached by
     * the transport builder so terminal middleware (T-CASA-023 correlation)
     * can read user/tenant ids WITHOUT re-authenticating.
     */
    private function attach(Request $request, ApplicationContext $context): ApplicationContext
    {
        $request->attributes->set(self::CONTEXT_ATTRIBUTE, $context);
        

        return $context;
    }

    private function extractBearerToken(Request $request): ?string
    {
        $header = $request->header('Authorization');

        if ($header === null) {
            return null;
        }

        if (!str_starts_with(strtolower(trim($header)), 'bearer ')) {
            return null;
        }

        return trim(substr($header, 7));
    }

    private function buildGuestContext(Request $request): ApplicationContext
    {
        return $this->base->forGuest([
            'locale' => $request->getPreferredLanguage([$this->base->toArray()['locale'] ?? 'en']) ?? 'en',
            'timezone' => $request->header('X-Timezone') ?? 'UTC',
            'tenantId' => $request->header('X-Tenant-ID'),
            'applicationId' => $request->header('X-Application-ID'),
            'deviceId' => $request->header('X-Device-ID'),
            'guard' => $request->attributes->get(self::GUARD_ATTRIBUTE) ?? $request->route('guard'),
            'requestId' => $request->attributes->get(self::REQUEST_ATTRIBUTE) ?? $request->header('X-Request-ID'),
            'correlationId' => $request->attributes->get(self::CORRELATION_ATTRIBUTE) ?? $request->header('X-Correlation-ID'),
        ]);
    }

    private function buildAuthenticatedContext(ApplicationIdentity $identity, Request $request): ApplicationContext
    {
        return $this->base->fromIdentity($identity, [
            'locale' => $request->getPreferredLanguage([$this->base->toArray()['locale'] ?? 'en']) ?? 'en',
            'timezone' => $request->header('X-Timezone') ?? 'UTC',
            'tenantId' => $request->header('X-Tenant-ID'),
            'applicationId' => $request->header('X-Application-ID'),
            'deviceId' => $request->header('X-Device-ID'),
            'guard' => $request->attributes->get(self::GUARD_ATTRIBUTE) ?? $request->route('guard'),
            'requestId' => $request->attributes->get(self::REQUEST_ATTRIBUTE) ?? $request->header('X-Request-ID') ?? $identity->attributes['request_id'] ?? null,
            'correlationId' => $request->attributes->get(self::CORRELATION_ATTRIBUTE) ?? $request->header('X-Correlation-ID') ?? $identity->attributes['correlation_id'] ?? null,
        ]);
    }
}