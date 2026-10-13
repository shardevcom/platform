<?php

declare(strict_types=1);

namespace Shardevcom\Configuration;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\ConfigurationProvider;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Core\Results\ApiMeta;

/**
 * Registry of configuration providers with partial failure support (T-080).
 *
 * Allows resolving multiple providers in a single call; if one provider
 * throws, it's included as an error in the result without affecting others.
 */
final class ConfigurationRegistry
{
    /**
     * @var array<string, ConfigurationProvider>
     */
    private array $providers = [];

    public function register(ConfigurationProvider $provider): void
    {
        $key = $provider->key();

        if (isset($this->providers[$key])) {
            throw new \InvalidArgumentException("Configuration provider '{$key}' is already registered.");
        }

        $this->providers[$key] = $provider;
    }

    public function has(string $key): bool
    {
        return isset($this->providers[$key]);
    }

    public function get(string $key): ConfigurationProvider
    {
        if (!isset($this->providers[$key])) {
            throw new ResourceNotFound('Configuration provider [' . $key . '] not found.');
        }

        return $this->providers[$key];
    }

    /**
     * @return array<string, ConfigurationProvider>
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Resolve one or more providers with visibility checks.
     *
     * Returns a map of key => result where each result is either:
     * - {status: 'success', data: mixed}
     * - {status: 'error', error: {code, message}}
     *
     * @param list<string> $keys
     * @return array<string, array{status: string, data?: mixed, error?: array{code: string, message: string}}>
     */
    public function resolve(array $keys, ApplicationContext $context): array
    {
        $results = [];

        foreach ($keys as $key) {
            if (!isset($this->providers[$key])) {
                $results[$key] = [
                    'status' => 'error',
                    'error' => [
                        'code' => 'NOT_FOUND',
                        'message' => "Configuration provider '{$key}' not found.",
                    ],
                ];
                continue;
            }

            $provider = $this->providers[$key];

            // Check visibility
            if (!$provider->visibility()->allows($context)) {
                $results[$key] = [
                    'status' => 'error',
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => "Access denied to configuration provider '{$key}'.",
                    ],
                ];
                continue;
            }

            try {
                $data = $provider->resolve($context);
                $results[$key] = [
                    'status' => 'success',
                    'data' => $data,
                ];
            } catch (\Throwable $e) {
                $results[$key] = [
                    'status' => 'error',
                    'error' => [
                        'code' => 'PROVIDER_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return $results;
    }
}