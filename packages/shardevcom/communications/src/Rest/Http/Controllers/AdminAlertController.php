<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Communications\Application\Commands\TriggerAdminAlert;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;

final class AdminAlertController
{
    public function __construct(
        private readonly TriggerAdminAlert $triggerAdminAlert,
        private readonly AuthorizationManager $authorization,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'send', $context);

        $payload = $request->input('payload', []);
        $roles = $request->input('roles', []);

        if (! is_array($payload)) {
            throw new ValidationFailed(['payload' => 'The payload must be an object.']);
        }

        if (! is_array($roles)) {
            throw new ValidationFailed(['roles' => 'The roles field must be an array.']);
        }

        return response()->json(ApiResponse::success(
            $this->triggerAdminAlert->execute(
                (string) $request->input('event_key', ''),
                $payload,
                array_values($roles),
                $request->input('locale'),
            ),
            'Administrative alert dispatched.',
            ApiMeta::fromContext($context),
        )->toArray());
    }
}
