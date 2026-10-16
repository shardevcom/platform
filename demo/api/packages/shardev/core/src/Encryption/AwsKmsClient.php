<?php

declare(strict_types=1);

namespace Shardevcom\Core\Encryption;

use Aws\Kms\KmsClient as AwsKmsClient;
use Aws\Exception\AwsException;
use Shardevcom\Core\Contracts\KmsClient;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;

/**
 * AWS KMS implementation.
 *
 * Uses AWS Key Management Service for encryption, decryption,
 * and blind index generation.
 *
 * Requires: aws/aws-sdk-php composer package
 * Configuration via environment variables:
 * - AWS_KMS_KEY_ID (key ARN or alias)
 * - AWS_REGION
 * - AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY (or IAM role)
 */
final class AwsKmsClient implements \Shardevcom\Core\Contracts\KmsClient
{
    private AwsKmsClient $client;
    private string $keyId;
    private string $keyArn;

    public function __construct(
        ?string $keyId = null,
        ?string $region = null
    ) {
        $this->keyId = $keyId ?? $_ENV['AWS_KMS_KEY_ID'] ?? throw new \LogicException('AWS_KMS_KEY_ID not set');
        $region = $region ?? $_ENV['AWS_REGION'] ?? 'us-east-1';

        $this->client = new AwsKmsClient([
            'region' => $region,
            'version' => 'latest',
        ]);

        // Resolve key ARN if alias was provided
        $this->keyArn = $this->resolveKeyArn($this->keyId);
    }

    public function encrypt(string $plaintext, array $additionalData = []): string
    {
        try {
            $result = $this->client->encrypt([
                'KeyId' => $this->keyArn,
                'Plaintext' => $plaintext,
                'EncryptionContext' => $additionalData,
            ]);

            return base64_encode($result['CiphertextBlob']);
        } catch (AwsException $e) {
            throw new BusinessRuleViolation('AWS KMS encryption failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function decrypt(string $ciphertext, array $additionalData = []): string
    {
        try {
            $result = $this->client->decrypt([
                'CiphertextBlob' => base64_decode($ciphertext),
                'EncryptionContext' => $additionalData,
            ]);

            return $result['Plaintext'];
        } catch (AwsException $e) {
            throw new BusinessRuleViolation('AWS KMS decryption failed: '.$e->getMessage(), 0, $e);
        }
    }

    public function blindIndex(string $plaintext, string $context = ''): string
    {
        // Use AWS KMS GenerateDataKeyWithoutPlaintext to get a deterministic key
        // for HMAC, or use a separate key
        $data = $context.'|'.$plaintext;
        $hmacKey = $this->deriveHmacKey();

        return hash_hmac('sha256', $data, $hmacKey);
    }

    public function getKeyId(): string
    {
        return $this->keyArn;
    }

    public function rotateKey(): void
    {
        // AWS KMS supports automatic key rotation
        // This enables automatic annual rotation
        $this->client->enableKeyRotation([
            'KeyId' => $this->keyArn,
        ]);
    }

    private function resolveKeyArn(string $keyId): string
    {
        // If it's already an ARN, return it
        if (str_starts_with($keyId, 'arn:aws:kms:')) {
            return $keyId;
        }

        // Otherwise, try to describe the key to get its ARN
        try {
            $result = $this->client->describeKey(['KeyId' => $keyId]);
            return $result['KeyMetadata']['Arn'];
        } catch (AwsException) {
            // If describe fails, assume it's a valid key ID
            return $keyId;
        }
    }

    private function deriveHmacKey(): string
    {
        // Derive a separate HMAC key for blind indexing
        // In production, use a dedicated key or GenerateDataKeyWithoutPlaintext
        return hash_hmac('sha256', 'blind-index-hmac-key', $this->keyArn);
    }
}