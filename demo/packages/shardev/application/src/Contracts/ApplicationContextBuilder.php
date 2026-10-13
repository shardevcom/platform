<?php

declare(strict_types=1);

namespace Shardevcom\Application\Contracts;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;

/**
 * Contract for building ApplicationContext from various transport sources.
 *
 * Implementations handle the specifics of extracting identity, permissions,
 * tenant, and observability IDs from their respective transport layer.
 */
interface ApplicationContextBuilder
{
    /**
     * Build context from an already-resolved ApplicationIdentity.
     *
     * This is the common path: authentication has already happened,
     * and we have the identity with its abilities/attributes.
     */
    public function fromIdentity(
        ApplicationIdentity $identity,
        array $additional = []
    ): ApplicationContext;

    /**
     * Build context for a guest (unauthenticated) request.
     */
    public function forGuest(array $additional = []): ApplicationContext;
}