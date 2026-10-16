<?php

declare(strict_types=1);

namespace Shardevcom\Core;

/**
 * Transport-agnostic execution context (T-011).
 *
 * Carries WHO/WHERE (user, tenant, application, device are independent —
 * a user_id is NEVER treated as a tenant), localization, granted
 * permissions/capabilities, and observability ids. Built from arrays, so
 * REST, GraphQL, CLI, Jobs, Events and WebSockets share one factory input
 * shape (builders land in T-091). Immutable: derive variants via with().
 *
 * Pure PHP: no HTTP, no Eloquent, no framework imports.
 */
final class ApplicationContext
{
    public function __construct(
        private ?string $userId = null,
        private ?string $tenantId = null,
        private ?string $applicationId = null,
        private ?string $deviceId = null,
        private ?string $guard = null,
        private string $locale = 'en',
        private string $timezone = 'UTC',
        /** @var list<string> permission strings like products.view */
        private array $permissions = [],
        /** @var list<string> capability strings */
        private array $capabilities = [],
        private ?string $requestId = null,
        private ?string $correlationId = null,
    ) {
        $this->requestId = $requestId ?? self::generateRequestId();
        $this->correlationId = $correlationId ?? $this->requestId;
    }

    /**
     * @param array{userId?: ?string, tenantId?: ?string, applicationId?: ?string, deviceId?: ?string, guard?: ?string, locale?: string, timezone?: string, permissions?: list<string>, capabilities?: list<string>, requestId?: ?string, correlationId?: ?string} $data
     */
    public static function create(array $data = []): self
    {
        return new self(
            userId: $data['userId'] ?? null,
            tenantId: $data['tenantId'] ?? null,
            applicationId: $data['applicationId'] ?? null,
            deviceId: $data['deviceId'] ?? null,
            guard: $data['guard'] ?? null,
            locale: $data['locale'] ?? 'en',
            timezone: $data['timezone'] ?? 'UTC',
            permissions: $data['permissions'] ?? [],
            capabilities: $data['capabilities'] ?? [],
            requestId: $data['requestId'] ?? null,
            correlationId: $data['correlationId'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $changes subset of create() keys
     */
    public function with(array $changes): self
    {
        return self::create(array_merge($this->toArray(), $changes));
    }

    public function userId(): ?string
    {
        return $this->userId;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function applicationId(): ?string
    {
        return $this->applicationId;
    }

    public function deviceId(): ?string
    {
        return $this->deviceId;
    }

    public function guard(): ?string
    {
        return $this->guard;
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * @return list<string>
     */
    public function capabilities(): array
    {
        return $this->capabilities;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }

    /**
     * @return array{userId: ?string, tenantId: ?string, applicationId: ?string, deviceId: ?string, guard: ?string, locale: string, timezone: string, permissions: list<string>, capabilities: list<string>, requestId: string, correlationId: string}
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'tenantId' => $this->tenantId,
            'applicationId' => $this->applicationId,
            'deviceId' => $this->deviceId,
            'guard' => $this->guard,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
            'permissions' => $this->permissions,
            'capabilities' => $this->capabilities,
            'requestId' => $this->requestId,
            'correlationId' => $this->correlationId,
        ];
    }

    private static function generateRequestId(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
