<?php

declare(strict_types=1);

namespace Shardevcom\Widgets\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Widgets\WidgetsRegistry;
use Shardevcom\Core\Results\ApiResponse;

/**
 * Widgets API Controller.
 *
 * GET /api/v1/widgets?include=card,chart,metric,table
 *
 * Returns partial results (207) if some providers fail or are forbidden.
 */
final class WidgetsController
{
    public function __construct(
        private readonly WidgetsRegistry $widgetsRegistry,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $include = $request->query('include', '');

        if ($include === '') {
            // Return all public providers by default
            $providers = array_filter(
                $this->widgetsRegistry->all(),
                fn ($provider) => $provider->visibility()->allows(
                    \Shardevcom\Core\ApplicationContext::create()
                )
            );
            $keys = array_keys($providers);
        } else {
            $keys = array_map('trim', explode(',', $include));
        }

        $context = $this->contextBuilder->fromRequest($request);
        $results = $this->widgetsRegistry->resolve($keys, $context);

        $hasErrors = array_reduce($results, fn ($carry, $result) => $carry || $result['status'] === 'error', false);

        if ($hasErrors) {
            $response = ApiResponse::partial($results, 'Widgets retrieved');
            return response()->json($response->toArray(), 207);
        }

        $response = ApiResponse::success($results, 'Widgets retrieved');
        return response()->json($response->toArray(), 200);
    }
}