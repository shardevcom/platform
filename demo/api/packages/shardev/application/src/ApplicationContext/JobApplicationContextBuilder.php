<?php

declare(strict_types=1);

namespace Shardevcom\Application\ApplicationContext;

use Shardevcom\Application\Contracts\ApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Contracts\PermissionResolver;

/**
 * Queue Job-specific ApplicationContext builder.
 *
 * Builds context from job payload, which should contain the
 * serialized ApplicationContext or its components.
 */
final class JobApplicationContextBuilder implements ApplicationContextBuilder
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
     * Build context from job payload.
     *
     * Expected payload keys (snake_case or camelCase):
     * - context: serialized ApplicationContext array (preferred)
     * - user_id/userId, tenant_id/tenantId, application_id/applicationId, device_id/deviceId
     * - locale, timezone
     * - permissions, capabilities
     * - request_id/requestId, correlation_id/correlationId
     *
     * If 'context' is present, it's used as base and other keys override.
     */
    public function fromPayload(array $payload): ApplicationContext
    {
        $normalized = $this->normalizeKeys($payload);

        if (isset($normalized['context']) && is_array($normalized['context'])) {
            $baseContext = ApplicationContext::create($normalized['context']);
            $overrides = $normalized;
            unset($overrides['context']);

            return $baseContext->with($overrides);
        }

        $userId = $normalized['userId'] ?? null;

        if ($userId === null) {
            return $this->base->forGuest([
                'tenantId' => $normalized['tenantId'] ?? null,
                'applicationId' => $normalized['applicationId'] ?? null,
                'deviceId' => $normalized['deviceId'] ?? null,
                'locale' => $normalized['locale'] ?? null,
                'timezone' => $normalized['timezone'] ?? null,
                'requestId' => $normalized['requestId'] ?? null,
                'correlationId' => $normalized['correlationId'] ?? null,
            ]);
        }

        $identity = new ApplicationIdentity(
            id: (string) $userId,
            kind: 'job',
            attributes: [
                'via' => 'job',
                'capabilities' => $normalized['capabilities'] ?? [],
            ],
        );

        return $this->base->fromIdentity($identity, [
            'tenantId' => $normalized['tenantId'] ?? null,
            'applicationId' => $normalized['applicationId'] ?? null,
            'deviceId' => $normalized['deviceId'] ?? null,
            'locale' => $normalized['locale'] ?? null,
            'timezone' => $normalized['timezone'] ?? null,
            'requestId' => $normalized['requestId'] ?? null,
            'correlationId' => $normalized['correlationId'] ?? null,
        ]);
    }

    /**
     * Normalize payload keys from snake_case to camelCase.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeKeys(array $payload): array
    {
        $map = [
            'user_id' => 'userId',
            'tenant_id' => 'tenantId',
            'application_id' => 'applicationId',
            'device_id' => 'deviceId',
            'request_id' => 'requestId',
            'correlation_id' => 'correlationId',
        ];

        $result = [];
        foreach ($payload as $key => $value) {
            $normalizedKey = $map[$key] ?? $key;
            $result[$normalizedKey] = $value;
        }

        return $result;
    }
}