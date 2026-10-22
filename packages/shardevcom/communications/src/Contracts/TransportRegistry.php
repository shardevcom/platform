<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Enums\Channel;

interface TransportRegistry
{
    /**
     * Returns null when the channel has no configured transport.
     */
    public function for(Channel $channel): ?ChannelTransport;
}
