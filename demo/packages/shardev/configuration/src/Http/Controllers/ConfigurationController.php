<?php

declare(strict_types=1);

namespace Shardevcom\Configuration\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Configuration\ConfigurationRegistry;
use Shardevcom\Core\Results\ApiResponse;

/**
 * GET /api/v1/configuration?include=core,preferences (T-072).
 *
 * Thin adapter: it translates an HTTP request into registry keys and frames
 * the result. It holds no configuration logic and decides nothing itself.
 *
 * Partial responses are the point of this endpoint: clients bootstrap from
 * several independent providers at once, so one unknown or forbidden key must
 * not cost the caller every other key it asked for. Any error in the set
 * downgrades the whole envelope to 207 partial with per-provider outcomes.
 */
final class ConfigurationController
{
    public function __construct(
        private readonly ConfigurationRegistry $registry,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $keys = $this->requestedKeys($request, $context);

        $results = $this->registry->resolve($keys, $context);

        $hasErrors = $this->hasErrors($results);

        $response = $hasErrors
            ? ApiResponse::partial($results, 'Configuration partially retrieved')
            : ApiResponse::success($results, 'Configuration retrieved');

        return response()->json($response->toArray(), $response->httpStatus);
    }

    /**
     * @return list<string>
     */
    private function requestedKeys(Request $request, \Shardevcom\Core\ApplicationContext $context): array
    {
        $include = trim((string) $request->query('include', ''));

        // An empty include means "give me what I am allowed to see", so the
        // caller never has to know the registered keys to bootstrap.
        if ($include === '') {
            return array_keys(array_filter(
                $this->registry->all(),
                static fn ($provider): bool => $provider->visibility()->allows($context),
            ));
        }

        return array_values(array_filter(array_map('trim', explode(',', $include))));
    }

    /**
     * @param array<string, array{status: string, data?: mixed, error?: array{code: string, message: string}}> $results
     */
    private function hasErrors(array $results): bool
    {
        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                return true;
            }
        }

        return false;
    }
}