<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Infrastructure\Transport;

use Shardevcom\Communications\Contracts\ChannelTransport;
use Shardevcom\Communications\Contracts\TransportRegistry;
use Shardevcom\Communications\Domain\Enums\Channel;

final class TransportManager implements TransportRegistry
{
    /**
     * @param array<string, ChannelTransport> $transports keyed by channel value
     */
    public function __construct(private readonly array $transports)
    {
    }

    public function for(Channel $channel): ?ChannelTransport
    {
        return $this->transports[$channel->value] ?? null;
    }
}
