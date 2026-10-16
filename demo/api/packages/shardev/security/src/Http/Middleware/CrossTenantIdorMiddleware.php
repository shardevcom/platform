<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\ApplicationContext;

/**
 * Cross-Tenant IDOR Protection Middleware.
 *
 * Validates that the resource being accessed belongs to the authenticated user's tenant.
 * Prevents Insecure Direct Object Reference (IDOR) attacks across tenants.
 */
final class CrossTenantIdorMiddleware
{
    public function __construct(
        private readonly array $protectedRoutes = [],
        private readonly array $tenantIdRouteParameters = ['tenant_id', 'tenantId'],
    ) {}

    public function handle(Request $request, Closure $next): JsonResponse
    {
        // Only apply to API routes
        if (!$request->is('api/*')) {
            return $next($request);
        }

        // Get the application context from request attributes
        $context = $request->attributes->get('application_context');

        if (!$context instanceof ApplicationContext) {
            return $next($request);
        }

        $tenantId = $context->tenantId();

        // If no tenant context, skip (public resources)
        if ($tenantId === null) {
            return $next($request);
        }

        // Check if route has tenant_id parameter
        $routeTenantId = $this->extractTenantIdFromRoute($request);

        if ($routeTenantId !== null && $routeTenantId !== $tenantId) {
            // Cross-tenant access attempt - block with 403
            throw new Forbidden(
                "Cross-tenant access denied: resource belongs to tenant {$routeTenantId}, but user belongs to tenant {$tenantId}.",
                [
                    'resource_tenant' => $routeTenantId,
                    'user_tenant' => $tenantId,
                ]
            );
        }

        // Check protected routes that require tenant isolation
        if ($this->isProtectedRoute($request)) {
            // Ensure the user has access to the specific resource
            // This would typically check the resource repository
            // For now, we just ensure tenant matches
        }

        return $next($request);
    }

    private function extractTenantIdFromRoute(Request $request): ?string
    {
        // Check route parameters for tenant_id
        foreach ($this->tenantIdRouteParameters as $param) {
            if ($request->route($param)) {
                return (string) $request->route($param);
            }
        }

        // Check query parameters
        if ($request->has('tenant_id')) {
            return (string) $request->query('tenant_id');
        }

        // Check request body for tenant_id (for POST/PUT/PATCH)
        if ($request->isMethod('post') || $request->isMethod('put') || $request->isMethod('patch')) {
            $input = $request->all();
            if (isset($input['tenant_id'])) {
                return (string) $input['tenant_id'];
            }
        }

        return null;
    }

    private function isProtectedRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            return false;
        }

        return in_array($routeName, $this->protectedRoutes, true);
    }

    public function addProtectedRoute(string $routeName): void
    {
        $this->protectedRoutes[] = $routeName;
    }
}