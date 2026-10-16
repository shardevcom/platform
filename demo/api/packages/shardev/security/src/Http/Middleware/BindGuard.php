<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Shardevcom\Core\ApplicationContext;

/**
 * Middleware to bind the current platform guard from the route prefix.
 *
 * Extracts the {guard} route parameter, validates it against config('platforms'),
 * sets it as the default auth guard, and stores it in the ApplicationContext.
 *
 * Lives in the security adapter: it is HTTP middleware, and core must not
 * depend on Illuminate\Http nor abort().
 *
 * @see T-204, T-CASA-012
 */
final class BindGuard
{
    public function __construct(
        private readonly Application $app,
        private readonly \Illuminate\Contracts\Auth\Factory $auth,
        private readonly \Illuminate\Contracts\Config\Repository $config,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $guard = $request->route('guard');

        if (! $guard) {
            return $next($request);
        }

        $platforms = $this->config->get('platforms.platforms', []);

        if (! isset($platforms[$guard])) {
            abort(404, "Platform guard [{$guard}] not found.");
        }

        // Set the guard for this request
        $this->auth->shouldUse($guard);

        // Store guard in ApplicationContext if resolved
        if ($this->app->bound(ApplicationContext::class)) {
            $context = $this->app->make(ApplicationContext::class);
            $this->app->instance(ApplicationContext::class, $context->with(['guard' => $guard]));
        }

        return $next($request);
    }
}
