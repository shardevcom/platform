<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Communications\Application\Commands\SetPreference;
use Shardevcom\Communications\Application\Queries\GetPreferences;
use Shardevcom\Core\Exceptions\Unauthorized;
use Shardevcom\Core\Results\ApiResponse;

final class PreferenceController
{
    public function __construct(
        private readonly GetPreferences $getPreferences,
        private readonly SetPreference $setPreference,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        [$guard, $userId] = $this->identity($request);

        return response()->json(ApiResponse::success(
            $this->getPreferences->execute($guard, $userId),
            'Communication preferences retrieved.',
        )->toArray());
    }

    public function optOut(Request $request): JsonResponse
    {
        [$guard, $userId] = $this->identity($request);

        $this->setPreference->execute(
            $guard,
            $userId,
            true,
            $request->input('channel'),
            $request->input('event_key'),
            $request->input('reason'),
        );

        return response()->json(ApiResponse::success(null, 'Communication opt-out saved.')->toArray());
    }

    public function optIn(Request $request): JsonResponse
    {
        [$guard, $userId] = $this->identity($request);

        $this->setPreference->execute(
            $guard,
            $userId,
            false,
            $request->input('channel'),
            $request->input('event_key'),
        );

        return response()->json(ApiResponse::success(null, 'Communication opt-in saved.')->toArray());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function identity(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            throw new Unauthorized('An authenticated user is required.');
        }

        $guard = (string) ($request->route('guard') ?? config('platforms.default', 'web'));
        $userId = (string) $user->getAuthIdentifier();

        return [$guard, $userId];
    }
}
