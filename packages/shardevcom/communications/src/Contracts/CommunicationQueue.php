<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

/**
 * Optional asynchronous delivery boundary.
 *
 * The dispatcher resolves templates, applies opt-outs and writes the log row
 * BEFORE calling this port, so a queued message is already fully auditable.
 */
interface CommunicationQueue
{
    public function push(int $messageId): void;
}
