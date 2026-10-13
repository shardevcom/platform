<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\ApplicationContext;

/**
 * Contextual row constraints for one definition (T-032).
 *
 * Derives column constraints from (definition, context): tenant partition
 * when the resource opted in AND the context carries a tenantId, owner
 * constraint when an ownerKey is set AND the context carries a userId.
 * A user_id is NEVER promoted to a tenant. Guest/system contexts simply
 * yield no constraint instead of inventing one. The persistence layer
 * (T-041) applies these as query filters.
 */
final class ResourceScope
{
    /**
     * @param array<string, string> $constraints column => value
     */
    private function __construct(private array $constraints)
    {
    }

    public static function for(ResourceDefinition $definition, ApplicationContext $context): self
    {
        $constraints = [];

        if ($definition->tenantScoped() && $context->tenantId() !== null) {
            $constraints['tenant_id'] = $context->tenantId();
        }

        if ($definition->ownerKey() !== null && $context->userId() !== null) {
            $constraints[$definition->ownerKey()] = $context->userId();
        }

        return new self($constraints);
    }

    /**
     * @return array<string, string>
     */
    public function constraints(): array
    {
        return $this->constraints;
    }

    public function isEmpty(): bool
    {
        return $this->constraints === [];
    }
}
