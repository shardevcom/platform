<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;

/**
 * Base implementation of ApplicationContextBuilder.
 *
 * Handles the common logic of converting an ApplicationIdentity into
 * an ApplicationContext, including permission extraction and
 * observability ID propagation.
 */
final class BaseApplicationContextBuilder implements ApplicationContextBuilder
{
    /**
     * @param array<string, mixed> $defaultAttributes
     */
    public function __construct(
        private readonly PermissionResolver $permissions,
        private readonly array $defaultAttributes = [],
    ) {}

    public function fromIdentity(
        ApplicationIdentity $identity,
        array $additional = []
    ): ApplicationContext {
        $permissions = $this->extractPermissions($identity);

        $data = [
            'userId' => $identity->id,
            'tenantId' => $this->resolveTenantId($identity, $additional),
            'applicationId' => $additional['applicationId'] ?? $this->defaultAttributes['application_id'] ?? null,
            'deviceId' => $this->resolveDeviceId($identity, $additional),
            'guard' => $additional['guard'] ?? $this->defaultAttributes['guard'] ?? null,
            'locale' => $additional['locale'] ?? $this->defaultAttributes['locale'] ?? 'en',
            'timezone' => $additional['timezone'] ?? $this->defaultAttributes['timezone'] ?? 'UTC',
            'permissions' => $permissions,
            'capabilities' => $this->resolveCapabilities($identity, $additional),
            'requestId' => $additional['requestId'] ?? $this->defaultAttributes['request_id'] ?? null,
            'correlationId' => $additional['correlationId'] ?? $additional['requestId'] ?? $this->defaultAttributes['correlation_id'] ?? null,
        ];

        return ApplicationContext::create($data);
    }

    public function forGuest(array $additional = []): ApplicationContext
    {
        $data = [
            'userId' => null,
            'tenantId' => $additional['tenantId'] ?? $this->defaultAttributes['tenant_id'] ?? null,
            'applicationId' => $additional['applicationId'] ?? $this->defaultAttributes['application_id'] ?? null,
            'deviceId' => $additional['deviceId'] ?? $this->defaultAttributes['device_id'] ?? null,
            'guard' => $additional['guard'] ?? $this->defaultAttributes['guard'] ?? null,
            'locale' => $additional['locale'] ?? $this->defaultAttributes['locale'] ?? 'en',
            'timezone' => $additional['timezone'] ?? $this->defaultAttributes['timezone'] ?? 'UTC',
            'permissions' => [],
            'capabilities' => [],
            'requestId' => $additional['requestId'] ?? $this->defaultAttributes['request_id'] ?? null,
            'correlationId' => $additional['correlationId'] ?? $additional['requestId'] ?? $this->defaultAttributes['correlation_id'] ?? null,
        ];

        return ApplicationContext::create($data);
    }

    /**
     * Extract permissions from identity attributes.
     *
     * The identity's 'abilities' attribute (from token) contains the granted permissions.
     */
    private function extractPermissions(ApplicationIdentity $identity): array
    {
        $abilities = $identity->attributes['abilities'] ?? [];

        if (!is_array($abilities)) {
            return [];
        }

        return array_values(array_unique(array_map('strval', $abilities)));
    }

    /**
     * Resolve tenant ID from identity or additional data.
     *
     * Tenant is NEVER derived from user_id - they are independent concepts.
     * Override in transport-specific builders if needed.
     */
    protected function resolveTenantId(ApplicationIdentity $identity, array $additional): ?string
    {
        // Check additional data first (e.g., from request header)
        if (isset($additional['tenantId'])) {
            return $additional['tenantId'];
        }

        // Check identity attributes (e.g., from token claims)
        if (isset($identity->attributes['tenant_id'])) {
            return (string) $identity->attributes['tenant_id'];
        }

        // Check default attributes (e.g., from config)
        return $this->defaultAttributes['tenant_id'] ?? null;
    }

    /**
     * Resolve device ID from identity or additional data.
     */
    protected function resolveDeviceId(ApplicationIdentity $identity, array $additional): ?string
    {
        if (isset($additional['deviceId'])) {
            return $additional['deviceId'];
        }

        if (isset($identity->attributes['device']) && is_array($identity->attributes['device'])) {
            return $identity->attributes['device']['name'] ?? null;
        }

        if (isset($identity->attributes['device_id'])) {
            return (string) $identity->attributes['device_id'];
        }

        return $this->defaultAttributes['device_id'] ?? null;
    }

    /**
     * Resolve capabilities from identity attributes.
     */
    protected function resolveCapabilities(ApplicationIdentity $identity, array $additional): array
    {
        $capabilities = [];

        if (isset($identity->attributes['capabilities']) && is_array($identity->attributes['capabilities'])) {
            $capabilities = $identity->attributes['capabilities'];
        }

        if (isset($additional['capabilities']) && is_array($additional['capabilities'])) {
            $capabilities = array_merge($capabilities, $additional['capabilities']);
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Get default attributes as array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->defaultAttributes;
    }
}