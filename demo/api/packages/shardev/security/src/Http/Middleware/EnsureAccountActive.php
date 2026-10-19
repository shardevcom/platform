<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Infrastructure\AccountLock;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from a disabled account even when the bearer token is still
 * valid, so disabling an account takes effect immediately and does not rely on
 * token revocation having propagated.
 *
 * It runs after `auth`, so an unauthenticated request is left to that
 * middleware to reject.
 */
final class EnsureAccountActive
{
    public function __construct(private readonly AccountLock $locks) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $guard = (string) ($request->route('guard') ?? config('platforms.default', 'web'));

        if ($this->locks->isDisabled($guard, $user->getAuthIdentifier())) {
            return response()->json(
                ApiResponse::error(new ApiError(
                    'ACCOUNT_DISABLED',
                    'This account has been disabled.',
                    [],
                    403,
                ))->toArray(),
                403,
            );
        }

        return $next($request);
    }
}