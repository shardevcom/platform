<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Domain\Data;

use Shardevcom\Communications\Domain\Enums\MessageStatus;

final class DeliveryResult
{
    public function __construct(
        public readonly MessageStatus $status,
        public readonly ?string $provider = null,
        public readonly ?string $providerMessageId = null,
        public readonly ?string $error = null,
    ) {
    }

    public static function sent(?string $provider = null, ?string $providerMessageId = null): self
    {
        return new self(MessageStatus::SENT, $provider, $providerMessageId);
    }

    public static function failed(string $error): self
    {
        return new self(MessageStatus::FAILED, error: $error);
    }

    public static function skipped(MessageStatus $status, string $reason): self
    {
        return new self($status, error: $reason);
    }

    public function isSuccessful(): bool
    {
        return $this->status === MessageStatus::SENT;
    }
}
