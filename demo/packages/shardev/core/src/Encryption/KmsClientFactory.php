<?php

declare(strict_types=1);

namespace Shardevcom\Core\Encryption;

use Shardevcom\Core\Contracts\KmsClient;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;

/**
 * Factory for creating KMS client instances.
 *
 * Supports multiple KMS providers through a unified interface.
 */
final class KmsClientFactory
{
    private static ?KmsClient $defaultClient = null;

    public static function create(string $provider = 'gcp', array $config = []): KmsClient
    {
        return match (strtolower($provider)) {
            'gcp', 'google', 'google-cloud' => new GoogleCloudKmsClient(
                $config['project_id'] ?? null,
                $config['location'] ?? null,
                $config['key_ring'] ?? null,
                $config['key_name'] ?? null
            ),
            'aws', 'amazon', 'aws-kms' => new AwsKmsClient(
                $config['key_id'] ?? null,
                $config['region'] ?? null
            ),
            default => throw new BusinessRuleViolation(
                "Unsupported KMS provider: {$provider}. Supported: gcp, aws"
            ),
        };
    }

    /**
     * Get or create the default KMS client.
     *
     * Uses the KMS_PROVIDER environment variable (default: gcp).
     */
    public static function getDefault(): KmsClient
    {
        if (self::$defaultClient !== null) {
            return self::$defaultClient;
        }

        $provider = $_ENV['KMS_PROVIDER'] ?? 'gcp';
        $config = [
            'project_id' => $_ENV['GOOGLE_CLOUD_PROJECT'] ?? null,
            'location' => $_ENV['GOOGLE_CLOUD_KMS_LOCATION'] ?? null,
            'key_ring' => $_ENV['GOOGLE_CLOUD_KMS_KEY_RING'] ?? null,
            'key_name' => $_ENV['GOOGLE_CLOUD_KMS_KEY_NAME'] ?? null,
            'key_id' => $_ENV['AWS_KMS_KEY_ID'] ?? null,
            'region' => $_ENV['AWS_REGION'] ?? null,
        ];

        return self::$defaultClient = self::create($_ENV['KMS_PROVIDER'] ?? 'gcp', $config);
    }

    /**
     * Set a custom default client (useful for testing).
     */
    public static function setDefault(KmsClient $client): void
    {
        self::$defaultClient = $client;
    }

    /**
     * Reset the default client (useful for testing).
     */
    public static function resetDefault(): void
    {
        self::$defaultClient = null;
    }
}