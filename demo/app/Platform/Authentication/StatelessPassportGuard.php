<?php

declare(strict_types=1);

namespace App\Platform\Authentication;

use Illuminate\Http\Request;
use Laravel\Passport\Guards\TokenGuard;

/**
 * Passport's guard without the cross-request user cache.
 *
 * AuthManager keeps one guard instance per name for the whole process, and
 * TokenGuard answers every later user() call from the user resolved on the
 * first one. In a long-lived worker — or in a test that issues two requests
 * with two different tokens — the second caller silently inherits the first
 * identity.
 *
 * Laravel rebinds the request container entry between requests, so dropping
 * the cached principal there is enough to make the guard correct per request
 * while still caching within one.
 */
final class StatelessPassportGuard extends TokenGuard
{
    public function setRequest(Request $request): static
    {
        $this->user = null;
        $this->client = null;

        return parent::setRequest($request);
    }
}
