<?php

declare(strict_types=1);

namespace Shardevcom\Core\Encryption;

use Google\Cloud\Kms\V1\KeyManagementServiceClient;
use Google\Cloud\Kms\V1\EncryptRequest;
use Google\Cloud\Kms\V1\DecryptRequest;
use Google\Cloud\Kms\V1\CryptoKeyVersion;
use Google\Protobuf\Internal\GPBUtil;
use Shardevcom\Core\Contracts\KmsClient;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;

/**
 * Google Cloud KMS implementation.
 *
 * Uses Google Cloud Key Management Service for encryption, decryption,
 * and blind index generation.
 *
 * Requires: google/cloud-kms composer package
 * Configuration via environment variables:
 * - GOOGLE_CLOUD_PROJECT
 * - GOOGLE_CLOUD_KMS_KEY_RING
 * - GOOGLE_CLOUD_KMS_KEY_NAME
 * - GOOGLE_CLOUD_KMS_LOCATION (default: global)
 */
final class GoogleCloudKmsClient implements \Shardevcom\Core\Contracts\KmsClient
{
    private KeyManagementServiceClient $client;
    private string $keyName;
    private string $keyId;

    public function __construct(
        ?string $projectId = null,
        ?string $locationId = null,
        ?string $keyRingId = null,
        ?string $keyId = null
    ) {
        $projectId ??= $_ENV['GOOGLE_CLOUD_PROJECT'] ?? throw new \LogicException('GOOGLE_CLOUD_PROJECT not set');
        $locationId ??= $_ENV['GOOGLE_CLOUD_KMS_LOCATION'] ?? 'global';
        $keyRingId ??= $_ENV['GOOGLE_CLOUD_KMS_KEY_RING'] ?? throw new \LogicException('GOOGLE_CLOUD_KMS_KEY_RING not set');
        $keyId ??= $_ENV['GOOGLE_CLOUD_KMS_KEY_NAME'] ?? throw new \LogicException('GOOGLE_CLOUD_KMS_KEY_NAME not set');

        $this->client = new KeyManagementServiceClient();
        $this->keyName = $this->client->cryptoKeyVersionName($projectId, $locationId, $keyRingId, $keyId, '1');
        $this->keyId = sprintf(
            'projects/%s/locations/%s/keyRings/%s/cryptoKeys/%s',
            $projectId,
            $locationId,
            $keyRingId,
            $keyId
        );
    }

    public function encrypt(string $plaintext, array $additionalData = []): string
    {
        $request = (new EncryptRequest())
            ->setName($this->keyName)
            ->setPlaintext($plaintext)
            ->setAdditionalAuthenticatedData($additionalData ? json_encode($additionalData) : '');

        $response = $this->client->encrypt($request);

        return base64_encode($response->getCiphertext());
    }

    public function decrypt(string $ciphertext, array $additionalData = []): string
    {
        $request = (new DecryptRequest())
            ->setName($this->keyName)
            ->setCiphertext(base64_decode($ciphertext))
            ->setAdditionalAuthenticatedData($additionalData ? json_encode($additionalData) : '');

        $response = $this->client->decrypt($request);

        return $response->getPlaintext();
    }

    public function blindIndex(string $plaintext, string $context = ''): string
    {
        // Use a separate key for blind indexing (HMAC)
        // In production, use a separate crypto key for blind indexing
        $data = $context.'|'.$plaintext;
        $key = $this->deriveHmacKey();

        return hash_hmac('sha256', $data, $key);
    }

    public function getKeyId(): string
    {
        return $this->keyId;
    }

    public function rotateKey(): void
    {
        // Key rotation in GCP KMS is managed through the console or IAM
        // This method can trigger a manual rotation if needed
        throw new BusinessRuleViolation(
            'Key rotation must be performed through Google Cloud Console or IAM'
        );
    }

    private function deriveHmacKey(): string
    {
        // Derive a separate HMAC key from the master key
        // In production, use a dedicated HMAC key in KMS
        return hash_hmac('sha256', 'blind-index-hmac-key', $this->keyId);
    }

    public function __destruct()
    {
        if (isset($this->client)) {
            $this->client->close();
        }
    }
}