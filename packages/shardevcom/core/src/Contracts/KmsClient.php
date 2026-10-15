<?php

declare(strict_types=1);

namespace Shardevcom\Core\Contracts;

/**
 * Interface for Key Management Service clients.
 *
 * Provides a unified interface for encrypting/decrypting data using
 * various KMS providers (Google Cloud KMS, AWS KMS, etc.).
 *
 * T-CASA-014: Blind index support for encrypted fields.
 */
interface KmsClient
{
    /**
     * Encrypt plaintext data.
     *
     * @param string $plaintext Data to encrypt
     * @param array<string, string> $additionalData Optional additional authenticated data
     * @return string Base64-encoded ciphertext
     */
    public function encrypt(string $plaintext, array $additionalData = []): string;

    /**
     * Decrypt ciphertext data.
     *
     * @param string $ciphertext Base64-encoded ciphertext to decrypt
     * @param array<string, string> $additionalData Optional additional authenticated data
     * @return string Decrypted plaintext
     */
    public function decrypt(string $ciphertext, array $additionalData = []): string;

    /**
     * Generate a deterministic hash for blind indexing.
     *
     * This creates a searchable hash of the plaintext without revealing
     * the actual value. Uses HMAC with a separate key from encryption.
     *
     * @param string $plaintext Data to hash
     * @param string $context Optional context to separate different index types
     * @return string Hex-encoded deterministic hash
     */
    public function blindIndex(string $plaintext, string $context = ''): string;

    /**
     * Get the key identifier/name for audit logging.
     */
    public function getKeyId(): string;

    /**
     * Rotate to a new key version (if supported by provider).
     */
    public function rotateKey(): void;
}