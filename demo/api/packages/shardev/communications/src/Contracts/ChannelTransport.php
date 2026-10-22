<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Contracts;

use Shardevcom\Communications\Domain\Data\DeliveryResult;
use Shardevcom\Communications\Domain\Data\OutboundMessage;

interface ChannelTransport
{
    public function send(OutboundMessage $message): DeliveryResult;
}
