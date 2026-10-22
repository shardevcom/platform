<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Queue;

use Shardevcom\Communications\Contracts\CommunicationQueue;
use Shardevcom\Communications\Jobs\SendCommunicationMessageJob;

final class LaravelCommunicationQueue implements CommunicationQueue
{
    public function push(int $messageId): void
    {
        $job = new SendCommunicationMessageJob($messageId);

        $connection = config('communications.queue.connection');
        $queue = config('communications.queue.queue');

        if (is_string($connection) && $connection !== '') {
            $job->onConnection($connection);
        }

        if (is_string($queue) && $queue !== '') {
            $job->onQueue($queue);
        }

        SendCommunicationMessageJob::dispatch($job);
    }
}
