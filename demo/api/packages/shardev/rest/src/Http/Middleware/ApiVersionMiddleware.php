<?php

declare(strict_types=1);

namespace Shardevcom\Rest\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * API Versioning Middleware (T-078).
 *
 * Adds versioning and deprecation headers to API responses:
 * - API-Version: Current API version
 * - Deprecation: true (if endpoint is deprecated)
 * - Link: <url>; rel="deprecation" (if deprecated)
 * - Sunset: date (if endpoint has sunset date)
 */
final class ApiVersionMiddleware
{
    public function __construct(
        private readonly string $defaultVersion = '1.0',
        private readonly array $deprecatedEndpoints = [],
        private readonly array $sunsetDates = [],
    ) {}

    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);

        // Only apply to API routes
        if (!$this->isApiRoute($request)) {
            return $response;
        }

        $version = $this->getApiVersion($request);
        $path = $request->path();

        // Add API-Version header
        $response->headers->set('API-Version', $version);

        // Check if endpoint is deprecated
        if (isset($this->deprecatedEndpoints[$path])) {
            $deprecationInfo = $this->deprecatedEndpoints[$path];
            $response->headers->set('Deprecation', 'true');

            if (isset($deprecationInfo['link'])) {
                $response->headers->set('Link', $deprecationInfo['link']);
            }
        }

        // Check if endpoint has sunset date
        if (isset($this->sunsetDates[$path])) {
            $response->headers->set('Sunset', $this->sunsetDates[$path]);
        }

        // Add Link header for API documentation if available.
        // ResponseHeaderBag has no addLink(); the Link header is formatted by hand.
        $docsUrl = config('rest.docs_url', '/docs/api');
        if ($docsUrl) {
            $response->headers->set('Link', '<'.$docsUrl.'>; rel="describedby"');
        }

        return $response;
    }

    private function isApiRoute(Request $request): bool
    {
        $path = $request->path();

        // Standard API routes: /api/...
        // Multi-platform API routes: /{guard}/api/... (e.g., /admin/api/v1/...)
        return str_starts_with($path, 'api/')
            || preg_match('#^[^/]+/api/#', $path) === 1;
    }

    private function getApiVersion(Request $request): string
    {
        // Check for version in Accept header (e.g., application/vnd.example.v2+json)
        $accept = $request->header('Accept');
        if ($accept && preg_match('/vnd\.\w+\.v(\d+)/', $accept, $matches)) {
            return 'v' . $matches[1];
        }

        // Check for version in URL path (e.g., /api/v2/...)
        if (preg_match('#^/api/v(\d+)#', $request->path(), $matches)) {
            return 'v' . $matches[1];
        }

        // Check for version in custom header
        $versionHeader = $request->header('API-Version');
        if ($versionHeader) {
            return 'v' . $versionHeader;
        }

        // Check query parameter
        $versionQuery = $request->query('version');
        if ($versionQuery) {
            return 'v' . $versionQuery;
        }

        return 'v' . $this->defaultVersion;
    }
}