<?php

declare(strict_types=1);

namespace Shardevcom\Core;

/**
 * Authenticated caller identity (T-011, completes the T-010
 * AuthenticationProvider contract).
 *
 * Minimal on purpose: a stable id, the kind of actor, and opaque
 * attributes owned by the provider implementation. Anything richer
 * (user model, tenant resolution) is built into ApplicationContext
 * by the T-091 builders, not here.
 */
final class ApplicationIdentity
{
    /**
     * @param array<string, mixed> $attributes provider-owned, never trusted blindly
     */
    public function __construct(
        public readonly string $id,
        public readonly string $kind = 'user',
        public readonly array $attributes = [],
    ) {
    }
}
