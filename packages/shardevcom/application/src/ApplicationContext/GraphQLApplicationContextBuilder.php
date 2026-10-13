<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;

/**
 * GraphQL-specific ApplicationContext builder.
 *
 * GraphQL context is typically built once per request and shared
 * across all resolvers. This builder extracts the context from
 * the GraphQL resolver context (usually set by middleware).
 */
final class GraphQLApplicationContextBuilder implements ApplicationContextBuilder
{
    public function __construct(
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
     * Build context from GraphQL resolver context.
     *
     * Expected context keys (set by GraphQL middleware):
     * - authentication: ApplicationIdentity (if authenticated)
     * - locale, timezone
     * - tenant_id, application_id, device_id
     * - request_id, correlation_id
     */
    public function fromResolverContext(array $context): ApplicationContext
    {
        $identity = $context['authentication'] ?? null;

        if ($identity instanceof ApplicationIdentity) {
            return $this->base->fromIdentity($identity, [
                'locale' => $context['locale'] ?? null,
                'timezone' => $context['timezone'] ?? null,
                'tenantId' => $context['tenant_id'] ?? null,
                'applicationId' => $context['application_id'] ?? null,
                'deviceId' => $context['device_id'] ?? null,
                'requestId' => $context['request_id'] ?? null,
                'correlationId' => $context['correlation_id'] ?? null,
            ]);
        }

        return $this->base->forGuest([
            'locale' => $context['locale'] ?? null,
            'timezone' => $context['timezone'] ?? null,
            'tenantId' => $context['tenant_id'] ?? null,
            'applicationId' => $context['application_id'] ?? null,
            'deviceId' => $context['device_id'] ?? null,
            'requestId' => $context['request_id'] ?? null,
            'correlationId' => $context['correlation_id'] ?? null,
        ]);
    }
}