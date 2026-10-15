<?php

declare(strict_types=1);

namespace Shardevcom\Core\Encryption;

use Shardevcom\Core\Contracts\BlindIndex;
use Shardevcom\Core\Contracts\KmsClient;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;

/**
 * Blind index implementation using KMS for HMAC key derivation.
 *
 * Creates deterministic hashes (blind indexes) for exact-match searching
 * of encrypted fields without revealing the plaintext.
 */
final class BlindIndexService implements BlindIndex
{
    private KmsClient $kms;

    public function __construct(?KmsClient $kms = null)
    {
        $this->kms = $kms ?? \Shardevcom\Core\Encryption\KmsClientFactory::getDefault();
    }

    public function create(string $plaintext, string $context): string
    {
        if ($plaintext === '') {
            throw new BusinessRuleViolation('Cannot create blind index for empty value');
        }

        // Normalize the plaintext for consistent indexing
        $normalized = $this->normalize($plaintext, $context);

        return $this->kms->blindIndex($normalized, $context);
    }

    public function verify(string $plaintext, string $index, string $context): bool
    {
        $expected = $this->create($plaintext, $context);
        return hash_equals($index, $expected);
    }

    public function createBatch(string $plaintext, array $contexts): array
    {
        $results = [];

        foreach ($contexts as $name => $context) {
            $results[$name] = $this->create($plaintext, $context);
        }

        return $results;
    }

    public function getKeyId(): string
    {
        return $this->kms->getKeyId();
    }

    /**
     * Normalize plaintext for consistent indexing.
     *
     * - Lowercase emails
     * - Remove formatting from phone numbers
     * - Trim whitespace
     */
    private function normalize(string $plaintext, string $context): string
    {
        $normalized = trim($plaintext);

        // Context-specific normalization
        return match ($context) {
            'email', 'emails' => mb_strtolower($normalized),
            'phone', 'phones' => preg_replace('/\D/', '', $normalized),
            default => $normalized,
        };
    }
}