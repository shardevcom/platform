<?php

declare(strict_types=1);

namespace Shardevcom\Core\Results;

use Shardevcom\Core\ApplicationContext;

/**
 * Request/response correlation envelope metadata (T-014).
 *
 * Carries observability ids plus an open extra bag for transport
 * details (e.g. validation failures under the 'details' key).
 */
final class ApiMeta
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public readonly string $requestId,
        public readonly string $correlationId,
        public readonly array $extra = [],
    ) {
    }

    public static function anonymous(): self
    {
        return new self('n/a', 'n/a');
    }

    public static function fromContext(ApplicationContext $context, array $extra = []): self
    {
        return new self($context->requestId(), $context->correlationId(), $extra);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function with(array $extra): self
    {
        return new self($this->requestId, $this->correlationId, array_merge($this->extra, $extra));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_merge([
            'request_id' => $this->requestId,
            'correlation_id' => $this->correlationId,
        ], $this->extra);
    }
}
