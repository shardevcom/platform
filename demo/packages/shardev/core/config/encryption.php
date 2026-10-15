<?php

declare(strict_types=1);

/**
 * Encryption & Blind Index Configuration
 *
 * T-CASA-014: Blind index support for encrypted fields.
 *
 * Supports multiple KMS providers:
 * - Google Cloud KMS (default)
 * - AWS KMS
 *
 * Configure via environment variables:
 *
 * KMS Provider:
 * - KMS_PROVIDER=gcp|aws (default: gcp)
 *
 * Google Cloud KMS:
 * - GOOGLE_CLOUD_PROJECT
 * - GOOGLE_CLOUD_KMS_LOCATION (default: global)
 * - GOOGLE_CLOUD_KMS_KEY_RING
 * - GOOGLE_CLOUD_KMS_KEY_NAME
 *
 * AWS KMS:
 * - AWS_KMS_KEY_ID (key ARN or alias)
 * - AWS_REGION (default: us-east-1)
 * - AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY (or IAM role)
 */

return [

    /*
     * Default KMS provider
     */
    'provider' => env('KMS_PROVIDER', 'gcp'),

    /*
     * Google Cloud KMS Configuration
     */
    'gcp' => [
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
        'location' => env('GOOGLE_CLOUD_KMS_LOCATION', 'global'),
        'key_ring' => env('GOOGLE_CLOUD_KMS_KEY_RING'),
        'key_name' => env('GOOGLE_CLOUD_KMS_KEY_NAME'),
    ],

    /*
     * AWS KMS Configuration
     */
    'aws' => [
        'key_id' => env('AWS_KMS_KEY_ID'),
        'region' => env('AWS_REGION', 'us-east-1'),
    ],

    /*
     * Blind Index Settings
     */
    'blind_index' => [
        /*
         * Default context prefix for all blind indexes.
         * Useful for multi-tenant applications.
         */
        'context_prefix' => env('BLIND_INDEX_CONTEXT_PREFIX', ''),

        /*
         * Hash algorithm for blind indexes.
         * Options: sha256, sha512
         */
        'algorithm' => env('BLIND_INDEX_ALGORITHM', 'sha256'),

        /*
         * Whether to log blind index operations (for audit).
         */
        'audit_log' => env('BLIND_INDEX_AUDIT_LOG', false),
    ],

    /*
     * Key Rotation
     */
    'key_rotation' => [
        'enabled' => env('KMS_KEY_ROTATION_ENABLED', true),
        'schedule' => env('KMS_KEY_ROTATION_SCHEDULE', 'yearly'),
    ],
];