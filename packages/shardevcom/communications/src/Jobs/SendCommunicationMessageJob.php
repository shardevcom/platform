<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Shardevcom\Communications\Application\MessageDeliveryService;

/**
 * Delivers an already resolved and logged message.
 *
 * The history row exists before this job is queued, so a worker failure never
 * loses the audit trail. Attempts are counted on the log row.
 */
final class SendCommunicationMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(public readonly int $messageId)
    {
        $this->tries = max(1, (int) config('communications.queue.tries', 3));
        $backoff = config('communications.queue.backoff', [10, 60]);
        $this->backoff = is_array($backoff)
            ? array_values(array_map('intval', $backoff))
            : [10, 60];
    }

    public function handle(MessageDeliveryService $delivery): void
    {
        $delivery->deliver($this->messageId, throwOnFailure: true);
    }
}
