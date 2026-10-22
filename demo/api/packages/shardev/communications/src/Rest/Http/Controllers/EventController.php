<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Communications\Application\Commands\SetEventChannels;
use Shardevcom\Communications\Application\Queries\ListEvents;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;

final class EventController
{
    public function __construct(
        private readonly ListEvents $listEvents,
        private readonly SetEventChannels $setEventChannels,
        private readonly AuthorizationManager $authorization,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'view', $context);

        return $this->respond(ApiResponse::success(
            $this->listEvents->execute(),
            'Communication events retrieved.',
            ApiMeta::fromContext($context),
        ));
    }

    public function channels(string $eventKey, Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'update', $context);

        $channels = $request->input('channels', []);

        if (! is_array($channels)) {
            throw new ValidationFailed(['channels' => 'The channels field must be an array.']);
        }

        return $this->respond(ApiResponse::success(
            $this->setEventChannels->execute($eventKey, array_values($channels)),
            'Communication event channels updated.',
            ApiMeta::fromContext($context),
        ));
    }

    private function respond(ApiResponse $response): JsonResponse
    {
        return response()->json($response->toArray());
    }
}
