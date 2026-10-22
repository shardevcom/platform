<?php

declare(strict_types=1);

namespace Shardevcom\Communications\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Communications\Application\Commands\SaveTemplate;
use Shardevcom\Communications\Application\Queries\ListTemplates;
use Shardevcom\Core\Contracts\AuthorizationManager;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;

final class TemplateController
{
    public function __construct(
        private readonly ListTemplates $listTemplates,
        private readonly SaveTemplate $saveTemplate,
        private readonly AuthorizationManager $authorization,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    public function index(string $eventKey, Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'view', $context);

        return $this->respond(ApiResponse::success(
            $this->listTemplates->execute($eventKey),
            'Communication templates retrieved.',
            ApiMeta::fromContext($context),
        ));
    }

    public function upsert(string $eventKey, Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);
        $this->authorization->authorize('communications', 'update', $context);

        return $this->respond(ApiResponse::success(
            $this->saveTemplate->execute($eventKey, $request->all()),
            'Communication template saved.',
            ApiMeta::fromContext($context),
        ));
    }

    private function respond(ApiResponse $response): JsonResponse
    {
        return response()->json($response->toArray());
    }
}
