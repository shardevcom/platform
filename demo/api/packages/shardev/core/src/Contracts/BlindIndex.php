<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Contract for blind index operations.
 *
 * Blind indexes allow searching encrypted fields without decrypting them.
 * They create deterministic hashes (HMAC) of the plaintext that can be
 * used for exact-match queries while keeping the actual data encrypted.
 *
 * T-CASA-014: Blind index support for encrypted fields.
 */
interface BlindIndex
{
    /**
     * Create a blind index value for the given plaintext.
     *
     * @param string $plaintext The value to index (e.g., email, phone)
     * @param string $context Context to separate different index types (e.g., 'email', 'phone')
     * @return string The blind index value (hex-encoded)
     */
    public function create(string $plaintext, string $context): string;

    /**
     * Verify if a plaintext matches a blind index.
     *
     * @param string $plaintext The plaintext to verify
     * @param string $index The stored blind index
     * @param string $context Context used when creating the index
     * @return bool True if the plaintext matches the index
     */
    public function verify(string $plaintext, string $index, string $context): bool;

    /**
     * Create multiple blind indexes for the same value with different contexts.
     *
     * Useful for creating indexes for different search patterns.
     *
     * @param string $plaintext The value to index
     * @param array<string, string> $contexts Map of context names to context values
     * @return array<string, string> Map of context names to blind index values
     */
    public function createBatch(string $plaintext, array $contexts): array;

    /**
     * Get the key identifier used for this blind index.
     */
    public function getKeyId(): string;
}